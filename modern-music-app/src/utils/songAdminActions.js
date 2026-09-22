// src/utils/songAdminActions.js — Admin-only song deletion & duplicate-merge API calls.
// Shared by <AdminMetadataEditor /> and any track list (e.g. AllSongs) so both
// surfaces hit the same secure PHP endpoints and update local state the same way.
import { getDeleteSongUrl, getMergeSongsUrl, getSubsonicAuthParams } from './api';

function buildAuthPayload(user) {
  const authParams = new URLSearchParams(getSubsonicAuthParams(user, true));
  return {
    token: localStorage.getItem('auth_token') || sessionStorage.getItem('active_session_token') || '',
    u: authParams.get('u') || user?.username || '',
    t: authParams.get('t') || '',
    s: authParams.get('s') || '',
  };
}

function authHeaders() {
  const token = localStorage.getItem('auth_token') || sessionStorage.getItem('active_session_token');
  const headers = { 'Content-Type': 'application/json' };
  if (token) headers['Authorization'] = `Bearer ${token}`;
  return headers;
}

// Deletes a single song from the Ampache DB (and optionally its physical file).
// Returns the parsed response on success; throws on non-200/error status.
export async function deleteSong(songId, user, { deleteFile = false } = {}) {
  const res = await fetch(getDeleteSongUrl(), {
    method: 'POST',
    headers: authHeaders(),
    body: JSON.stringify({
      song_id: songId,
      delete_file: deleteFile,
      ...buildAuthPayload(user),
    }),
  });
  const data = await res.json().catch(() => ({}));
  if (!res.ok || data.status !== 'success') {
    throw new Error(data.message || `HTTP ${res.status}`);
  }
  return data;
}

// Merges duplicate songs into a single primary song, reassigning play history,
// ratings, favorites, and playlist entries before deleting the duplicate rows.
export async function mergeSongs(primarySongId, duplicateSongIds, user) {
  const res = await fetch(getMergeSongsUrl(), {
    method: 'POST',
    headers: authHeaders(),
    body: JSON.stringify({
      primary_song_id: primarySongId,
      duplicate_song_ids: duplicateSongIds,
      ...buildAuthPayload(user),
    }),
  });
  const data = await res.json().catch(() => ({}));
  if (!res.ok || data.status !== 'success') {
    throw new Error(data.message || `HTTP ${res.status}`);
  }
  return data;
}
