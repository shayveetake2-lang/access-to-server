/**
 * Utility functions for Artist normalization, feature extraction, and deduplication
 */

/**
 * Strips featured artist suffixes (e.g. "feat.", "ft.", "featuring", "with", etc.)
 * to extract the primary artist name.
 */
export function extractPrimaryArtistName(rawName) {
  if (!rawName || typeof rawName !== 'string') return '';
  let cleaned = rawName.trim();

  // Pattern 1: Parenthetical features: "Artist (feat. Other)" or "Artist [ft. Other]"
  cleaned = cleaned.replace(/\s*[\(\[]\s*(?:feat\.?|ft\.?|featuring|with)\s+[^)\]]+[\)\]]/gi, '');

  // Pattern 2: Inline features: "Artist feat. Other", "Artist ft. Other", "Artist featuring Other", "Artist with Other"
  cleaned = cleaned.replace(/\s+(?:feat\.?|ft\.?|featuring|with)\s+.+$/gi, '');

  // Pattern 3: Trailing ampersand collaborations if primary exists: "Artist & Other"
  // Keep intact unless primary part is clearly an established artist
  return cleaned.trim();
}

/**
 * Normalizes an artist name into a canonical comparison key
 * (lowercase, strips accents/diacritics, strips leading "The ", removes special characters)
 */
export function normalizeArtistKey(name) {
  if (!name || typeof name !== 'string') return '';
  return name
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '') // remove diacritics
    .toLowerCase()
    .replace(/^the\s+/i, '') // strip leading "The "
    .replace(/[^\w\s]/gi, '') // strip punctuation
    .replace(/\s+/g, ' ')
    .trim();
}

/**
 * Merges raw artists list from Ampache Subsonic API.
 * - Identifies primary artists vs featured appearances.
 * - Groups features and similar names under the canonical artist profile.
 * - Enriches each artist with genres collected from album data.
 *
 * @param {Array} rawArtists - Array of artist objects from getArtists
 * @param {Array} rawAlbums - Array of album objects from getAlbumList (optional)
 * @returns {Array} Cleaned, merged list of canonical artists
 */
export function mergeArtists(rawArtists = [], rawAlbums = []) {
  if (!Array.isArray(rawArtists) || rawArtists.length === 0) return [];

  // 1. Build a map of artistId & artistName -> Set of Genres from albums
  const genresByArtistKey = new Map();
  const genresByArtistId = new Map();

  if (Array.isArray(rawAlbums)) {
    rawAlbums.forEach(album => {
      const genre = album.genre?.trim();
      if (!genre) return;

      if (album.artistId) {
        const idKey = String(album.artistId);
        if (!genresByArtistId.has(idKey)) genresByArtistId.set(idKey, new Set());
        genresByArtistId.get(idKey).add(genre);
      }

      if (album.artist) {
        const nameKey = normalizeArtistKey(extractPrimaryArtistName(album.artist));
        if (!genresByArtistKey.has(nameKey)) genresByArtistKey.set(nameKey, new Set());
        genresByArtistKey.get(nameKey).add(genre);
      }
    });
  }

  // 2. Identify all canonical artists by normalized key
  // A primary artist is an artist whose name does not contain a feature or is the shortest version.
  const canonicalMap = new Map(); // normalizedKey -> canonicalArtistObject

  // First pass: register standalone/primary artists
  rawArtists.forEach(artist => {
    const rawName = artist.name || '';
    const primaryName = extractPrimaryArtistName(rawName);
    const primaryKey = normalizeArtistKey(primaryName);
    const isFeature = primaryName.toLowerCase() !== rawName.trim().toLowerCase();

    if (!isFeature) {
      // It's a clean standalone artist name
      if (!canonicalMap.has(primaryKey)) {
        canonicalMap.set(primaryKey, {
          id: artist.id,
          name: artist.name,
          coverArt: artist.coverArt || null,
          albumCount: parseInt(artist.albumCount || 0, 10),
          aliasIds: [artist.id],
          aliasNames: [artist.name],
          genres: new Set()
        });
      } else {
        // Artist with identical normalized key (e.g. "The Weeknd" vs "Weeknd")
        const existing = canonicalMap.get(primaryKey);
        existing.albumCount += parseInt(artist.albumCount || 0, 10);
        existing.aliasIds.push(artist.id);
        existing.aliasNames.push(artist.name);
        if (!existing.coverArt && artist.coverArt) {
          existing.coverArt = artist.coverArt;
        }
      }
    }
  });

  // Second pass: handle featured artists and any artists not yet registered
  rawArtists.forEach(artist => {
    const rawName = artist.name || '';
    const primaryName = extractPrimaryArtistName(rawName);
    const primaryKey = normalizeArtistKey(primaryName);

    if (canonicalMap.has(primaryKey)) {
      const canonical = canonicalMap.get(primaryKey);
      // If this was a featured variation (e.g. "Drake feat. 21 Savage")
      if (!canonical.aliasIds.includes(artist.id)) {
        canonical.aliasIds.push(artist.id);
        canonical.aliasNames.push(artist.name);
        canonical.albumCount += parseInt(artist.albumCount || 0, 10);
        if (!canonical.coverArt && artist.coverArt) {
          canonical.coverArt = artist.coverArt;
        }
      }
    } else {
      // Primary artist wasn't in the list standalone, register this entry as its own canonical artist
      canonicalMap.set(primaryKey, {
        id: artist.id,
        name: artist.name,
        coverArt: artist.coverArt || null,
        albumCount: parseInt(artist.albumCount || 0, 10),
        aliasIds: [artist.id],
        aliasNames: [artist.name],
        genres: new Set()
      });
    }
  });

  // 3. Attach genres to each canonical artist
  const mergedList = Array.from(canonicalMap.values()).map(artist => {
    const primaryKey = normalizeArtistKey(artist.name);
    const genresSet = new Set();

    // From name-based map
    if (genresByArtistKey.has(primaryKey)) {
      genresByArtistKey.get(primaryKey).forEach(g => genresSet.add(g));
    }

    // From ID-based map
    artist.aliasIds.forEach(id => {
      if (genresByArtistId.has(String(id))) {
        genresByArtistId.get(String(id)).forEach(g => genresSet.add(g));
      }
    });

    return {
      ...artist,
      genres: Array.from(genresSet)
    };
  });

  // 4. Sort alphabetically by name
  mergedList.sort((a, b) => a.name.localeCompare(b.name, undefined, { sensitivity: 'base' }));

  return mergedList;
}
