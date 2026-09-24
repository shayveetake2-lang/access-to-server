// Auto-merges exact duplicate songs (same title, similar duration) returned by
// the Subsonic API so track lists and search results only render one clean entry.
// Duplicate IDs are preserved on the surviving entry as `duplicateIds` so admin
// tools (e.g. Orphaned Media / Content Assigner) can still act on every copy.

const normalizeTitle = (title) => (title || '').trim().toLowerCase().replace(/\s+/g, ' ');

/**
 * @param {Array} songs - raw Subsonic song objects (must have `id`, `title`, `duration`)
 * @param {number} durationToleranceSeconds - max duration delta to still count as a duplicate
 * @returns {Array} deduped songs; merged entries gain `duplicateIds` and `mergedCount`
 */
export function dedupeSongs(songs, durationToleranceSeconds = 1) {
  if (!Array.isArray(songs) || songs.length === 0) return [];

  const groups = [];
  const groupIndexByTitle = new Map();

  songs.forEach((song) => {
    if (!song) return;
    const titleKey = normalizeTitle(song.title);
    const duration = Number(song.duration) || 0;
    const candidateIndexes = groupIndexByTitle.get(titleKey) || [];

    const matchIdx = candidateIndexes.find((idx) => {
      const canonicalDuration = Number(groups[idx].canonical.duration) || 0;
      return Math.abs(canonicalDuration - duration) <= durationToleranceSeconds;
    });

    if (matchIdx !== undefined) {
      groups[matchIdx].duplicateIds.push(song.id);
    } else {
      groups.push({ canonical: song, duplicateIds: [] });
      groupIndexByTitle.set(titleKey, [...candidateIndexes, groups.length - 1]);
    }
  });

  return groups.map(({ canonical, duplicateIds }) => (
    duplicateIds.length === 0
      ? canonical
      : { ...canonical, duplicateIds, mergedCount: duplicateIds.length + 1 }
  ));
}

/**
 * Creates a persistent lookup index for use with `dedupeAppend()`.
 * Keep one of these per song list (e.g. in a useRef) across paginated fetches.
 */
export function createDedupeIndex() {
  return new Map(); // titleKey -> [{ resultIndex, duration }]
}

/**
 * Incrementally merges a new page of songs into an already-deduped result array
 * in O(newSongs.length) time, instead of re-scanning the whole (growing) list on
 * every page — critical for infinite-scrolling a 10,000+ song library smoothly.
 *
 * @param {Array} existingResult - previously deduped song array
 * @param {Array} newSongs - the newly fetched raw page of songs
 * @param {Map} index - a persistent index from `createDedupeIndex()`; mutated in place
 * @param {number} durationToleranceSeconds
 * @returns {Array} the new deduped result array (existingResult is not mutated)
 */
export function dedupeAppend(existingResult, newSongs, index, durationToleranceSeconds = 1) {
  const result = existingResult.slice();

  (newSongs || []).forEach((song) => {
    if (!song) return;
    const titleKey = normalizeTitle(song.title);
    const duration = Number(song.duration) || 0;
    const candidates = index.get(titleKey) || [];

    const match = candidates.find((c) => Math.abs(c.duration - duration) <= durationToleranceSeconds);
    if (match) {
      const target = result[match.resultIndex];
      const duplicateIds = [...(target.duplicateIds || []), song.id];
      result[match.resultIndex] = { ...target, duplicateIds, mergedCount: duplicateIds.length + 1 };
    } else {
      result.push(song);
      candidates.push({ resultIndex: result.length - 1, duration });
      index.set(titleKey, candidates);
    }
  });

  return result;
}

