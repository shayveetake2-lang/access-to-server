/**
 * storageSanitizer.js — Self-executing localStorage/sessionStorage hygiene pass.
 *
 * Runs once at app boot (imported by main.jsx) to clean up:
 *  - Invalid JSON under keys that are supposed to hold JSON
 *  - Legacy/orphaned Subsonic keys from older builds of Aether
 *  - Plain-text legacy credential objects (unhashed password, no subsonic_token/salt)
 *  - Expired sessionStorage catalog/search/chart caches (defensive TTL backstop)
 *
 * IMPORTANT: index.html/js/admin_auth.js (ServerFlow dashboard) shares the same
 * origin's storage. This sanitizer only ever touches keys it recognizes as
 * belonging to Aether (the "aether_" / "ampache_" prefixes below) so ServerFlow's
 * own auth_token/user_role/theme keys are never touched.
 */

// Keys that must hold valid JSON; deleted outright if parsing fails or shape is wrong.
const AETHER_JSON_KEYS = ['ampache_user', 'aether_queue_state', 'aether_liked_ids'];

// Keys that hold plain strings — never JSON-validated, never deleted for "invalid JSON".
const AETHER_PLAIN_KEYS = ['aether_theme', 'aether_audio_sink_id', 'aether_salt'];

// Legacy/orphaned key name fragments from older Aether/Subsonic prototypes.
// Any top-level key matching one of these prefixes is stale and removed unconditionally.
const LEGACY_KEY_PREFIXES = ['subsonic_', 'aether_legacy_', 'ampache_session_'];

// Defensive ceiling for any sessionStorage entry that looks like a
// { timestamp, data } cache payload. Individual features apply their own
// (shorter) TTLs on read; this is just a backstop for stale tabs left open for days.
const MAX_CACHE_AGE_MS = 1000 * 60 * 60 * 24; // 24 hours

function isPlainObject(value) {
  return value !== null && typeof value === 'object' && !Array.isArray(value);
}

/** A legacy, unhashed credential object: has a raw password but no precomputed Subsonic hash. */
function isPlaintextLegacyCredential(parsed) {
  return isPlainObject(parsed) && typeof parsed.password === 'string' && !parsed.subsonic_token && !parsed.subsonic_salt && !parsed.token;
}

function sanitizeKnownJsonKey(storage, key) {
  const raw = storage.getItem(key);
  if (raw === null) return;
  try {
    const parsed = JSON.parse(raw);
    if (key === 'ampache_user' && isPlaintextLegacyCredential(parsed)) {
      storage.removeItem(key);
    }
  } catch {
    // Not valid JSON — corrupted, remove it.
    storage.removeItem(key);
  }
}

function sanitizeLegacyOrphans(storage) {
  const keys = Object.keys(storage);
  keys.forEach((key) => {
    if (LEGACY_KEY_PREFIXES.some((prefix) => key.startsWith(prefix))) {
      storage.removeItem(key);
    }
  });
}

/** Removes expired `{ timestamp, data }` cache entries under the aether_ namespace in sessionStorage. */
function sanitizeExpiredCaches(storage) {
  const keys = Object.keys(storage);
  keys.forEach((key) => {
    if (!key.startsWith('aether_')) return;
    if (AETHER_JSON_KEYS.includes(key) || AETHER_PLAIN_KEYS.includes(key)) return;

    const raw = storage.getItem(key);
    if (raw === null) return;
    try {
      const parsed = JSON.parse(raw);
      if (isPlainObject(parsed) && typeof parsed.timestamp === 'number') {
        if (Date.now() - parsed.timestamp > MAX_CACHE_AGE_MS) {
          storage.removeItem(key);
        }
      }
      // Valid JSON without a timestamp (e.g. scroll positions/letters) is left alone.
    } catch {
      // Invalid JSON under an aether_ cache key — corrupted, remove it.
      storage.removeItem(key);
    }
  });
}

export function sanitizeClientStorage() {
  if (typeof window === 'undefined') return;

  [window.localStorage, window.sessionStorage].forEach((storage) => {
    if (!storage) return;
    try {
      AETHER_JSON_KEYS.forEach((key) => sanitizeKnownJsonKey(storage, key));
      sanitizeLegacyOrphans(storage);
    } catch {
      // Storage inaccessible (private browsing lockdown, quota errors, etc.) — skip silently.
    }
  });

  try {
    sanitizeExpiredCaches(window.sessionStorage);
  } catch {
    // ignore
  }
}
