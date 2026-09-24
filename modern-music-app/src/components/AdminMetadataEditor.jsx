import { useState, useEffect, useMemo } from 'react';
import { 
  Users, Music, Disc, GitMerge, Search, CheckSquare, Square, 
  AlertTriangle, CheckCircle2, RefreshCw, ShieldAlert, ArrowRight,
  Filter, Sparkles, Database, UserPlus, Unlink, Link2, Loader2, Pencil, X, Trash2
} from 'lucide-react';
import { useAuth } from '../context/AuthContext';
import { useToast } from '../context/ToastContext';
import { getMergeMetadataUrl } from '../utils/api';
import { adminPost } from './admin/adminApi';
import { deleteSong, mergeSongs } from '../utils/songAdminActions';

import { useSearchParams } from 'react-router-dom';

export default function AdminMetadataEditor() {
  const [searchParams] = useSearchParams();
  const initialMergeType = searchParams.get('mergeType');
  const initialMergeId = searchParams.get('mergeId');

  const { user, getAuthParams } = useAuth();
  const { showToast } = useToast();

  const [activeTab, setActiveTab] = useState(initialMergeType ? `${initialMergeType}s` : 'smart_merge'); 
  const [filterQuery, setFilterQuery] = useState('');

  // We set the initial search directly if we are targeting an ID
  // Wait, we need to find the name of the artist/album/song by ID, but that requires data to load first.
  // We'll handle selection after data loads.
  const [preselectTarget, setPreselectTarget] = useState(initialMergeId);

  const [artists, setArtists] = useState([]);
  const [albums, setAlbums] = useState([]);
  const [songs, setSongs] = useState([]);
  const [duplicates, setDuplicates] = useState([]);
  const [similarGroups, setSimilarGroups] = useState([]);

  const [selectedIds, setSelectedIds] = useState(new Set());
  const [targetArtistId, setTargetArtistId] = useState('');
  const [targetArtistSearch, setTargetArtistSearch] = useState('');


  const [loading, setLoading] = useState(false);
  const [merging, setMerging] = useState(false);
  const [mergingGroupId, setMergingGroupId] = useState(null);
  const [batchMerging, setBatchMerging] = useState(false);
  const [lastResult, setLastResult] = useState(null);
  const [unlinking, setUnlinking] = useState(false);

  // Create Custom Artist tab state
  const [newArtistName, setNewArtistName] = useState('');
  const [creatingArtist, setCreatingArtist] = useState(false);
  const [newlyCreatedArtist, setNewlyCreatedArtist] = useState(null);
  const [linkSongIds, setLinkSongIds] = useState(new Set());
  const [linkSongSearch, setLinkSongSearch] = useState('');
  const [linkingSongs, setLinkingSongs] = useState(false);
  const [linkResult, setLinkResult] = useState(null);


  const isAdmin = user?.role === 'admin' || user?.isAdmin === true || ['admin', 'musicadmin'].includes(user?.username?.toLowerCase());

  const getAuthToken = () => {
    if (user?.token) return user.token;
    try {
      const stored = localStorage.getItem('ampache_user') || sessionStorage.getItem('ampache_user');
      return stored ? JSON.parse(stored)?.token || '' : '';
    } catch {
      return '';
    }
  };

  const getAuthHeaders = () => {
    const token = getAuthToken();
    const headers = { 'Content-Type': 'application/json' };
    if (token) {
      headers['Authorization'] = `Bearer ${token}`;
    }
    return headers;
  };

  // 1. Fetch initial artists list and potential duplicates
  const fetchMetadata = async (query = '') => {
    if (!isAdmin) return;
    setLoading(true);
    try {
      // Fetch artists via Subsonic or merge API — send full u/t/s token+salt auth
      // (a bare username with no token is no longer accepted server-side).
      const authQ = `&${getAuthParams(user)}`;
      const searchQ = query ? `&q=${encodeURIComponent(query)}` : '';
      const resArtists = await fetch(`${getMergeMetadataUrl()}?action=search_artists&limit=500${searchQ}${authQ}`, {
        headers: getAuthHeaders()
      });
      const dataArtists = await resArtists.json();
      if (dataArtists?.status === 'success') {
        setArtists(dataArtists.artists || []);
      }

      // Fetch duplicate & fuzzy similar artist groups
      try {
        const resDupes = await fetch(`${getMergeMetadataUrl()}?action=find_similar_artists${authQ}`, {
          headers: getAuthHeaders()
        });
        const dataDupes = await resDupes.json();
        if (dataDupes?.status === 'success') {
          setSimilarGroups(dataDupes.groups || []);
          setDuplicates(dataDupes.duplicates || []);
        }
      } catch (e) {}

      // Fetch albums for album mode — server-side LIKE search against name AND
      // artist, with no restrictive pagination cap, so nothing gets hidden.
      if (activeTab === 'albums') {
        const resAlb = await fetch(`${getMergeMetadataUrl()}?action=search_albums&limit=500${searchQ}${authQ}`, {
          headers: getAuthHeaders()
        });
        const dataAlb = await resAlb.json();
        if (dataAlb?.status === 'success') {
          const albList = (dataAlb.albums || []).map(a => ({
            id: a.id,
            name: a.name,
            title: a.name,
            artist: a.artist_name || 'Unassigned',
            artistId: a.album_artist,
            year: a.year,
            songCount: a.song_count,
          }));
          setAlbums(albList);
        }
      }

      // Fetch songs for song mode — server-side LIKE search against title AND
      // artist, including orphaned tracks (artist/album IS NULL or 0).
      if (activeTab === 'songs' || activeTab === 'create_artist') {
        const resSongs = await fetch(`${getMergeMetadataUrl()}?action=search_songs&limit=500${searchQ}${authQ}`, {
          headers: getAuthHeaders()
        });
        const dataSongs = await resSongs.json();
        if (dataSongs?.status === 'success') {
          const songList = (dataSongs.songs || []).map(s => ({
            id: s.id,
            title: s.title,
            artist: s.artist_name || 'Unassigned',
            artistId: s.artist,
            album: s.album_name || 'Unassigned',
            albumId: s.album,
            duration: s.duration,
          }));
          setSongs(songList);
        }
      }

    } catch (err) {
      console.error("Failed fetching metadata:", err);
      showToast("Error loading catalog metadata", "error");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchMetadata();
    setSelectedIds(new Set());
    setLastResult(null);
    setNewlyCreatedArtist(null);
    setLinkSongIds(new Set());
    setLinkResult(null);
  }, [activeTab, isAdmin]);

  // Pre-select target once data loads
  useEffect(() => {
    if (preselectTarget) {
      if (activeTab === 'artists' && artists.length > 0) {
        setSelectedIds(new Set([preselectTarget]));
        setPreselectTarget(null);
      } else if (activeTab === 'albums' && albums.length > 0) {
        setSelectedIds(new Set([preselectTarget]));
        setPreselectTarget(null);
      } else if (activeTab === 'songs' && songs.length > 0) {
        setSelectedIds(new Set([preselectTarget]));
        setPreselectTarget(null);
      }
    }
  }, [artists, albums, songs, activeTab, preselectTarget]);

  // Debounced server-side re-search whenever the filter bar query changes, so
  // matches outside the initially-loaded page are still found (fixes missing
  // items in Artists / Albums / Songs merge search).
  useEffect(() => {
    if (!isAdmin) return undefined;
    const handle = setTimeout(() => {
      fetchMetadata(filterQuery.trim());
    }, 350);
    return () => clearTimeout(handle);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [filterQuery]);

  // Target artist candidates matching search query
  const targetCandidates = useMemo(() => {
    if (!targetArtistSearch.trim()) return artists.slice(0, 30);
    const q = targetArtistSearch.toLowerCase().trim();
    return artists.filter(a => a.name && a.name.toLowerCase().includes(q));
  }, [artists, targetArtistSearch]);

  const selectedTargetArtist = useMemo(() => {
    return artists.find(a => String(a.id) === String(targetArtistId));
  }, [artists, targetArtistId]);

  // Filtered source list based on search bar
  const displayedItems = useMemo(() => {
    const q = filterQuery.toLowerCase().trim();
    if (activeTab === 'artists') {
      if (!q) return artists.slice(0, 150);
      return artists.filter(a => a.name && a.name.toLowerCase().includes(q)).slice(0, 150);
    }
    if (activeTab === 'albums') {
      if (!q) return albums.slice(0, 150);
      return albums.filter(a => 
        (a.title && a.title.toLowerCase().includes(q)) || 
        (a.artist && a.artist.toLowerCase().includes(q))
      ).slice(0, 150);
    }
    if (activeTab === 'songs') {
      if (!q) return songs.slice(0, 150);
      return songs.filter(s => 
        (s.title && s.title.toLowerCase().includes(q)) || 
        (s.artist && s.artist.toLowerCase().includes(q))
      ).slice(0, 150);
    }
    return [];
  }, [activeTab, artists, albums, songs, filterQuery]);

  const toggleSelect = (id) => {
    setSelectedIds(prev => {
      const next = new Set(prev);
      if (next.has(id)) {
        next.delete(id);
      } else {
        next.add(id);
      }
      return next;
    });
  };

  const selectAll = () => {
    const ids = displayedItems.map(i => i.id).filter(id => String(id) !== String(targetArtistId));
    setSelectedIds(new Set(ids));
  };

  const deselectAll = () => {
    setSelectedIds(new Set());
  };

  // Execute Merge / Remap without native Chrome browser popups
  const handleExecuteMerge = async (e) => {
    if (e && typeof e.preventDefault === 'function') {
      e.preventDefault();
    }

    if (!targetArtistId) {
      showToast("Please choose a Target Artist Profile", "warning");
      return;
    }
    if (selectedIds.size === 0) {
      showToast("Please select at least one item to merge or remap", "warning");
      return;
    }

    setMerging(true);
    setLastResult(null);

    try {
      const authParams = new URLSearchParams(getAuthParams(user));
      const payload = {
        target_artist_id: parseInt(targetArtistId, 10),
        token: getAuthToken(),
        u: authParams.get('u') || '',
        t: authParams.get('t') || '',
        s: authParams.get('s') || '',
      };

      if (activeTab === 'artists') {
        payload.artist_ids = Array.from(selectedIds);
      } else if (activeTab === 'albums') {
        payload.album_ids = Array.from(selectedIds);
      } else if (activeTab === 'songs') {
        payload.song_ids = Array.from(selectedIds);
      }

      const res = await fetch(getMergeMetadataUrl(), {
        method: 'POST',
        headers: getAuthHeaders(),
        body: JSON.stringify(payload)
      });

      const data = await res.json();

      if (res.ok && data.status === 'success') {
        const exactMsg = "No double ups or duplicates found.";
        setLastResult({ ...data, message: exactMsg });
        showToast(exactMsg, "success");
        setSelectedIds(new Set());
        fetchMetadata(); // Refresh catalog state
      } else {
        showToast(data.message || "Failed to merge metadata", "error");
      }
    } catch (err) {
      console.error(err);
      showToast("Network error executing merge", "error");
    } finally {
      setMerging(false);
    }
  };

  // 1-Click merge for a single detected similar artist group (No browser dialog popup)
  const handleMergeGroup = async (e, group, customTargetId = null) => {
    if (e && typeof e.preventDefault === 'function') {
      e.preventDefault();
    }

    // Support both handleMergeGroup(group) and handleMergeGroup(e, group)
    const actualGroup = group || e;
    const targetId = customTargetId || actualGroup?.target_artist?.id;
    if (!actualGroup || !actualGroup.target_artist) return;

    const allMembers = [actualGroup.target_artist, ...(actualGroup.duplicates || [])];
    const sourceIds = allMembers.filter(m => String(m.id) !== String(targetId)).map(m => m.id);

    if (sourceIds.length === 0) return;

    setMergingGroupId(actualGroup.key);
    try {
      const authParams = new URLSearchParams(getAuthParams(user));
      const payload = {
        target_artist_id: parseInt(targetId, 10),
        artist_ids: sourceIds,
        token: getAuthToken(),
        u: authParams.get('u') || '',
        t: authParams.get('t') || '',
        s: authParams.get('s') || '',
      };

      const res = await fetch(getMergeMetadataUrl(), {
        method: 'POST',
        headers: getAuthHeaders(),
        body: JSON.stringify(payload)
      });
      const data = await res.json();
      if (res.ok && data.status === 'success') {
        const exactMsg = "No double ups or duplicates found.";
        showToast(exactMsg, "success");
        setSimilarGroups(prev => prev.filter(g => g.key !== actualGroup.key));
        fetchMetadata();
      } else {
        showToast(data.message || "Failed to merge artist profiles", "error");
      }
    } catch (err) {
      console.error(err);
      showToast("Network error executing merge", "error");
    } finally {
      setMergingGroupId(null);
    }
  };

  // Batch merge for all detected similar artist groups (No browser dialog popup)
  const handleMergeAllSimilar = async (e) => {
    if (e && typeof e.preventDefault === 'function') {
      e.preventDefault();
    }
    if (similarGroups.length === 0) return;

    setBatchMerging(true);
    try {
      const batchList = similarGroups.map(g => ({
        target_artist_id: g.target_artist.id,
        artist_ids: g.duplicates.map(d => d.id)
      }));

      const authParams = new URLSearchParams(getAuthParams(user));
      const payload = {
        batch: batchList,
        token: getAuthToken(),
        u: authParams.get('u') || '',
        t: authParams.get('t') || '',
        s: authParams.get('s') || '',
      };

      const res = await fetch(getMergeMetadataUrl(), {
        method: 'POST',
        headers: getAuthHeaders(),
        body: JSON.stringify(payload)
      });
      const data = await res.json();
      if (res.ok && data.status === 'success') {
        const exactMsg = "No double ups or duplicates found.";
        showToast(exactMsg, "success");
        setSimilarGroups([]);
        fetchMetadata();
      } else {
        showToast(data.message || "Failed to batch merge artists", "error");
      }
    } catch (err) {
      console.error(err);
      showToast("Network error executing batch merge", "error");
    } finally {
      setBatchMerging(false);
    }
  };

  // Detach selected albums/songs from their artist (route to "Unknown Artist")
  const handleUnlinkFromArtist = async (e) => {
    if (e && typeof e.preventDefault === 'function') e.preventDefault();
    if (activeTab !== 'songs' && activeTab !== 'albums') return;
    if (selectedIds.size === 0) {
      showToast("Select at least one item to unlink", "warning");
      return;
    }

    setUnlinking(true);
    try {
      const type = activeTab === 'songs' ? 'song' : 'album';
      const res = await adminPost('unlinkMedia', { type, ids: Array.from(selectedIds) });
      showToast(res.message || `${type}(s) unlinked from artist`, "success");
      setSelectedIds(new Set());
      fetchMetadata();
    } catch (err) {
      console.error(err);
      showToast(err.message || "Failed to unlink from artist", "error");
    } finally {
      setUnlinking(false);
    }
  };

  // Create a brand new custom artist row directly in the Ampache `artist` table
  const handleCreateArtist = async (e) => {
    e.preventDefault();
    if (!newArtistName.trim()) return;
    setCreatingArtist(true);
    try {
      const trimmedName = newArtistName.trim();
      const res = await adminPost('createArtist', { name: trimmedName });
      const newArtistId = res.artist_id || res.id;
      setNewlyCreatedArtist({ id: newArtistId, name: trimmedName });
      showToast(res.message || "Artist created", "success");

      // Inject the new artist into the active dropdown/list state immediately —
      // no hard refresh required to select or link songs to it.
      setArtists(prev => {
        if (prev.some(a => String(a.id) === String(newArtistId))) return prev;
        const injected = { id: newArtistId, name: trimmedName, song_count: 0, album_count: 0 };
        return [...prev, injected].sort((a, b) => (a.name || '').localeCompare(b.name || ''));
      });

      setNewArtistName('');
    } catch (err) {
      showToast(err.message || "Failed to create artist", "error");
    } finally {
      setCreatingArtist(false);
    }
  };

  // Link the selected existing songs to the newly created custom artist
  const handleLinkSongsToNewArtist = async (e) => {
    e.preventDefault();
    if (!newlyCreatedArtist || linkSongIds.size === 0) return;
    setLinkingSongs(true);
    setLinkResult(null);
    try {
      const res = await adminPost('linkSongsToArtist', {
        songIds: Array.from(linkSongIds),
        artistId: newlyCreatedArtist.id,
      });
      setLinkResult({ success: true, message: res.message });
      showToast(res.message || "Songs linked", "success");
      setLinkSongIds(new Set());
    } catch (err) {
      setLinkResult({ success: false, message: err.message });
      showToast(err.message || "Failed to link songs", "error");
    } finally {
      setLinkingSongs(false);
    }
  };

  const linkSongCandidates = useMemo(() => {
    if (!linkSongSearch.trim()) return songs;
    const q = linkSongSearch.toLowerCase().trim();
    return songs.filter(s => 
      (s.title && s.title.toLowerCase().includes(q)) || 
      (s.artist && s.artist.toLowerCase().includes(q))
    );
  }, [songs, linkSongSearch]);

  const toggleLinkSong = (id) => {
    setLinkSongIds(prev => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id); else next.add(id);
      return next;
    });
  };

  // Jumps to the manual merge panel with this artist/album pre-selected as the merge target
  const handleOpenInMerger = (item, e) => {
    if (e && typeof e.stopPropagation === 'function') e.stopPropagation();
    if (activeTab === 'albums') {
      const artistId = item.artistId || item.artist_id;
      if (!artistId) {
        showToast("This album has no linked artist to merge into", "warning");
        return;
      }
      setTargetArtistId(String(artistId));
      setTargetArtistSearch(item.artist || '');
    } else {
      setTargetArtistId(String(item.id));
      setTargetArtistSearch(item.name || '');
    }
    setActiveTab('artists');
    setSelectedIds(new Set());
    showToast(`"${item.name || item.title}" set as merge target`, "success");
  };

  // Inline rename state for song titles / album names
  const [editingItemId, setEditingItemId] = useState(null);
  const [editingValue, setEditingValue] = useState('');
  const [savingRename, setSavingRename] = useState(false);

  // Single-song deletion state
  const [deletingSongId, setDeletingSongId] = useState(null);

  // Duplicate song merge modal state
  const [showMergeSongsModal, setShowMergeSongsModal] = useState(false);
  const [mergePrimaryId, setMergePrimaryId] = useState('');
  const [mergingSongs, setMergingSongs] = useState(false);

  // Deletes a single song from Ampache (and optionally its physical file),
  // then instantly filters it out of every locally-held list.
  const handleDeleteSong = async (item, e) => {
    if (e) e.stopPropagation();
    if (!window.confirm(`Permanently delete "${item.title || item.name}"? This cannot be undone.`)) return;
    const deleteFile = window.confirm(
      `Also permanently delete the physical audio file from /Volumes/Music?\n\nOK = delete file too\nCancel = keep the file on disk, only remove it from the library`
    );

    setDeletingSongId(item.id);
    try {
      const res = await deleteSong(item.id, user, { deleteFile });
      setSongs(prev => prev.filter(s => s.id !== item.id));
      setSelectedIds(prev => {
        const next = new Set(prev);
        next.delete(item.id);
        return next;
      });
      if (deleteFile && res.file_error) {
        showToast(`"${item.title || item.name}" deleted, but file removal failed: ${res.file_error}`, 'warning');
      } else {
        showToast(`"${item.title || item.name}" deleted${res.file_deleted ? ' (file removed from disk)' : ''}`, 'success');
      }
    } catch (err) {
      showToast(err.message || 'Failed to delete song', 'error');
    } finally {
      setDeletingSongId(null);
    }
  };

  // Opens the "choose primary" modal for the currently selected duplicate songs
  const handleOpenMergeSongsModal = () => {
    if (selectedIds.size < 2) {
      showToast('Select at least 2 songs to merge', 'warning');
      return;
    }
    setMergePrimaryId(String(Array.from(selectedIds)[0]));
    setShowMergeSongsModal(true);
  };

  // Executes the merge: reassigns play history/ratings/playlist entries from
  // every non-primary selected song into the chosen primary, then deletes them.
  const handleConfirmMergeSongs = async () => {
    const allSelected = Array.from(selectedIds);
    const primaryId = parseInt(mergePrimaryId, 10);
    const duplicateIds = allSelected.filter(id => id !== primaryId);

    if (!primaryId || duplicateIds.length === 0) {
      showToast('Choose a primary track to keep', 'warning');
      return;
    }

    setMergingSongs(true);
    try {
      const res = await mergeSongs(primaryId, duplicateIds, user);
      showToast(`Merged ${res.deleted_count || duplicateIds.length} duplicate(s) into "${res.primary_title}"`, 'success');
      setSongs(prev => prev.filter(s => !duplicateIds.includes(s.id)));
      setSelectedIds(new Set());
      setShowMergeSongsModal(false);
      setMergePrimaryId('');
    } catch (err) {
      showToast(err.message || 'Failed to merge songs', 'error');
    } finally {
      setMergingSongs(false);
    }
  };

  const startEditing = (item, e) => {
    e.stopPropagation();
    setEditingItemId(item.id);
    setEditingValue(item.title || item.name || '');
  };

  const cancelEditing = (e) => {
    if (e) e.stopPropagation();
    setEditingItemId(null);
    setEditingValue('');
  };

  const handleSaveRename = async (item, e) => {
    if (e) e.stopPropagation();
    const value = editingValue.trim();
    if (!value) return;
    setSavingRename(true);
    try {
      const type = activeTab === 'songs' ? 'song' : 'album';
      const res = await adminPost('renameMedia', { type, id: item.id, value });
      showToast(res.message || 'Renamed successfully', 'success');
      if (type === 'song') {
        setSongs(prev => prev.map(s => s.id === item.id ? { ...s, title: value } : s));
      } else {
        setAlbums(prev => prev.map(a => a.id === item.id ? { ...a, title: value, name: value } : a));
      }
      setEditingItemId(null);
      setEditingValue('');
    } catch (err) {
      showToast(err.message || 'Rename failed', 'error');
    } finally {
      setSavingRename(false);
    }
  };

  if (!isAdmin) {
    return (
      <div className="p-8 rounded-2xl bg-red-950/20 border border-red-500/20 text-center max-w-lg mx-auto mt-12">
        <ShieldAlert size={36} className="text-red-400 mx-auto mb-3" />
        <h3 className="text-lg font-bold text-white">Administrator Access Required</h3>
        <p className="text-sm text-slate-400 mt-1">
          Only server administrators can remap songs, merge artists, and modify Ampache database metadata.
        </p>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Header Banner */}
      <div className="bg-gradient-to-br from-purple-950/40 via-slate-900 to-indigo-950/30 border border-purple-500/20 rounded-2xl p-5 sm:p-6 backdrop-blur-sm shadow-xl">
        <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
          <div>
            <div className="flex items-center gap-2 text-xs font-semibold text-purple-400 uppercase tracking-wider mb-1">
              <Sparkles size={14} />
              <span>Catalog Sanitizer & Metadata Remapper</span>
            </div>
            <h2 className="text-xl sm:text-2xl font-bold text-white flex items-center gap-2.5">
              <span>Admin Metadata Editor</span>
              <span className="text-xs px-2.5 py-0.5 rounded-full bg-purple-500/20 text-purple-300 border border-purple-500/30">
                mac2 drive
              </span>
            </h2>
            <p className="text-xs sm:text-sm text-slate-400 mt-1 max-w-2xl">
              Clean messy ID3 tags, deduplicate artist profiles, and remap tracks or albums to their canonical artist profile on Ampache.
            </p>
          </div>
          <button 
            onClick={fetchMetadata} 
            disabled={loading}
            className="self-start md:self-auto px-3.5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-medium flex items-center gap-1.5 transition-colors border border-white/5 disabled:opacity-50"
          >
            <RefreshCw size={14} className={loading ? "animate-spin" : ""} />
            <span>Refresh Data</span>
          </button>
        </div>

        {/* Duplicate Suspects Alert */}
        {duplicates.length > 0 && (
          <div className="mt-4 p-3 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-start gap-2.5 text-xs text-amber-200">
            <AlertTriangle size={16} className="text-amber-400 shrink-0 mt-0.5" />
            <div>
              <span className="font-semibold">Detected {duplicates.length} duplicate artist group(s) in database! </span>
              <span>(e.g., &quot;{duplicates[0].name1}&quot; vs &quot;{duplicates[0].name2}&quot;). Switch to &quot;Duplicate Artists&quot; mode below to merge them.</span>
            </div>
          </div>
        )}
      </div>

      {/* Operation Mode Tabs */}
      <div className="flex flex-wrap items-center gap-2 border-b border-white/10 pb-3">
        <button
          onClick={() => setActiveTab('smart_merge')}
          className={`px-4 py-2 rounded-xl text-xs sm:text-sm font-semibold flex items-center gap-2 transition-all ${
            activeTab === 'smart_merge'
              ? 'bg-purple-500 text-white shadow-lg shadow-purple-500/25'
              : 'bg-slate-900/60 text-slate-400 hover:text-white border border-white/5'
          }`}
        >
          <Sparkles size={16} />
          <span>Smart Similar Artists</span>
          {similarGroups.length > 0 && (
            <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-purple-700 text-purple-100 border border-purple-400/30">
              {similarGroups.length}
            </span>
          )}
        </button>

        <button
          onClick={() => setActiveTab('artists')}
          className={`px-4 py-2 rounded-xl text-xs sm:text-sm font-semibold flex items-center gap-2 transition-all ${
            activeTab === 'artists'
              ? 'bg-purple-500 text-white shadow-lg shadow-purple-500/25'
              : 'bg-slate-900/60 text-slate-400 hover:text-white border border-white/5'
          }`}
        >
          <Users size={16} />
          <span>Manual Artist Merge</span>
        </button>

        <button
          onClick={() => setActiveTab('albums')}
          className={`px-4 py-2 rounded-xl text-xs sm:text-sm font-semibold flex items-center gap-2 transition-all ${
            activeTab === 'albums'
              ? 'bg-purple-500 text-white shadow-lg shadow-purple-500/25'
              : 'bg-slate-900/60 text-slate-400 hover:text-white border border-white/5'
          }`}
        >
          <Disc size={16} />
          <span>Remap Albums</span>
        </button>

        <button
          onClick={() => setActiveTab('songs')}
          className={`px-4 py-2 rounded-xl text-xs sm:text-sm font-semibold flex items-center gap-2 transition-all ${
            activeTab === 'songs'
              ? 'bg-purple-500 text-white shadow-lg shadow-purple-500/25'
              : 'bg-slate-900/60 text-slate-400 hover:text-white border border-white/5'
          }`}
        >
          <Music size={16} />
          <span>Remap Individual Songs</span>
        </button>

        <button
          onClick={() => setActiveTab('create_artist')}
          className={`px-4 py-2 rounded-xl text-xs sm:text-sm font-semibold flex items-center gap-2 transition-all ${
            activeTab === 'create_artist'
              ? 'bg-purple-500 text-white shadow-lg shadow-purple-500/25'
              : 'bg-slate-900/60 text-slate-400 hover:text-white border border-white/5'
          }`}
        >
          <UserPlus size={16} />
          <span>Create Custom Artist</span>
        </button>
      </div>

      {activeTab === 'create_artist' ? (
        /* Create Custom Artist & Link Songs View */
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-5 items-start">
          <div className="lg:col-span-5 bg-slate-900/70 border border-white/10 rounded-2xl p-4 sm:p-5 space-y-4">
            <div>
              <label className="text-xs font-semibold text-purple-300 uppercase tracking-wider block mb-1.5 flex items-center gap-1.5">
                <UserPlus size={13} />
                <span>Step 1: Create Artist Profile</span>
              </label>
              <p className="text-xs text-slate-400 mb-3">
                Inserts a brand new row directly into the Ampache <code>artist</code> table.
              </p>
              <form onSubmit={handleCreateArtist} className="space-y-3">
                <input
                  value={newArtistName}
                  onChange={(e) => setNewArtistName(e.target.value)}
                  placeholder="e.g. My Custom Compilation Artist"
                  className="w-full bg-slate-950 border border-white/10 rounded-xl px-4 py-2.5 text-xs sm:text-sm text-white placeholder-slate-500 focus:outline-none focus:border-purple-500 transition-colors"
                  maxLength={255}
                />
                <button
                  type="submit"
                  disabled={creatingArtist || !newArtistName.trim()}
                  className="w-full py-2.5 px-4 rounded-xl bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 disabled:opacity-40 text-white font-semibold text-xs sm:text-sm flex items-center justify-center gap-2 shadow-lg shadow-purple-500/20 transition-all active:scale-[0.98]"
                >
                  {creatingArtist ? <Loader2 size={15} className="animate-spin" /> : <UserPlus size={15} />}
                  <span>Create Artist</span>
                </button>
              </form>
            </div>

            {newlyCreatedArtist && (
              <div className="p-3.5 rounded-xl bg-purple-500/10 border border-purple-500/30">
                <div className="text-xs text-purple-300 font-semibold mb-1 flex items-center gap-1">
                  <CheckCircle2 size={14} /> Active Custom Artist
                </div>
                <div className="text-base font-bold text-white truncate">{newlyCreatedArtist.name}</div>
                <div className="text-xs text-slate-400 mt-0.5">Database ID: {newlyCreatedArtist.id}</div>
              </div>
            )}

            {newlyCreatedArtist && (
              <div className="pt-2 border-t border-white/5 space-y-2">
                <div className="flex items-center justify-between text-xs text-slate-300 font-medium">
                  <span>Songs to link:</span>
                  <span className="font-mono text-purple-400 font-bold">{linkSongIds.size}</span>
                </div>
                <button
                  onClick={handleLinkSongsToNewArtist}
                  disabled={linkingSongs || linkSongIds.size === 0}
                  className="w-full py-3 px-4 rounded-xl bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 disabled:opacity-40 text-white font-semibold text-xs sm:text-sm flex items-center justify-center gap-2 shadow-lg shadow-purple-500/20 transition-all active:scale-[0.98]"
                >
                  {linkingSongs ? (
                    <>
                      <div className="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></div>
                      <span>Linking Songs...</span>
                    </>
                  ) : (
                    <>
                      <Link2 size={17} />
                      <span>Link Selected Songs</span>
                    </>
                  )}
                </button>
              </div>
            )}

            {linkResult && (
              <div className={`p-3.5 rounded-xl text-xs space-y-1 ${linkResult.success ? 'bg-emerald-500/10 border border-emerald-500/30 text-emerald-200' : 'bg-red-500/10 border border-red-500/30 text-red-300'}`}>
                {linkResult.message}
              </div>
            )}
          </div>

          <div className="lg:col-span-7 bg-slate-900/70 border border-white/10 rounded-2xl p-4 sm:p-5 flex flex-col min-h-[480px]">
            <div className="mb-3">
              <span className="text-xs font-semibold text-purple-300 uppercase tracking-wider block">
                Step 2: Select Songs to Link
              </span>
              <p className="text-xs text-slate-400">
                {newlyCreatedArtist ? `Choose songs to attach to "${newlyCreatedArtist.name}".` : 'Create an artist above first, then choose songs to attach.'}
              </p>
            </div>

            <div className="relative mb-3">
              <Search size={14} className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-500" />
              <input
                type="text"
                placeholder="Filter songs by title or artist..."
                value={linkSongSearch}
                onChange={(e) => setLinkSongSearch(e.target.value)}
                className="w-full bg-slate-950 border border-white/10 rounded-xl pl-9 pr-3 py-2 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-purple-500 transition-colors"
              />
            </div>

            <div className="flex-1 overflow-y-auto max-h-[420px] divide-y divide-white/5 border border-white/5 rounded-xl bg-slate-950/60 p-1">
              {loading ? (
                <div className="p-12 text-center text-slate-400 text-xs flex flex-col items-center justify-center">
                  <div className="w-6 h-6 border-2 border-purple-500 border-t-transparent rounded-full animate-spin mb-2"></div>
                  Loading songs...
                </div>
              ) : linkSongCandidates.length === 0 ? (
                <div className="p-12 text-center text-slate-500 text-xs">No songs found matching your filter.</div>
              ) : (
                linkSongCandidates.map((song) => {
                  const isSelected = linkSongIds.has(song.id);
                  return (
                    <div
                      key={song.id}
                      onClick={() => toggleLinkSong(song.id)}
                      className={`flex items-center justify-between p-2.5 rounded-lg cursor-pointer transition-all ${isSelected ? 'bg-purple-500/20 border border-purple-500/30 shadow-sm' : 'hover:bg-white/5'}`}
                    >
                      <div className="flex items-center gap-3 min-w-0 flex-1 pr-2">
                        <button type="button" className="text-purple-400 shrink-0">
                          {isSelected ? <CheckSquare size={16} /> : <Square size={16} className="text-slate-600" />}
                        </button>
                        <div className="min-w-0 flex-1">
                          <p className={`text-xs font-semibold truncate ${isSelected ? 'text-purple-300' : 'text-slate-200'}`}>{song.title}</p>
                          <p className="text-[11px] text-slate-400 truncate">Artist: {song.artist || 'Unknown'} • Album: {song.album || 'Unknown'}</p>
                        </div>
                      </div>
                      <span className="text-[10px] font-mono text-slate-500 shrink-0">ID: {song.id}</span>
                    </div>
                  );
                })
              )}
            </div>

            <div className="mt-3 flex items-center justify-between text-[11px] text-slate-400">
              <span>Showing {linkSongCandidates.length} songs</span>
              <span className="font-semibold text-purple-400">{linkSongIds.size} selected</span>
            </div>
          </div>
        </div>
      ) : activeTab === 'smart_merge' ? (
        /* Smart Similar Artists View */
        <div className="space-y-4">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-4 rounded-2xl bg-slate-900/80 border border-white/10">
            <div>
              <h3 className="text-sm font-bold text-white flex items-center gap-2">
                <span>Fuzzy Artist Matching Engine</span>
                <span className="text-[10px] px-2 py-0.5 rounded-full bg-purple-500/20 text-purple-300 font-normal">
                  Active across all users
                </span>
              </h3>
              <p className="text-xs text-slate-400 mt-0.5">
                Automatically detects variations in accents, leading articles (&quot;The&quot;), featured collaborator splits, and punctuation.
              </p>
            </div>
            {similarGroups.length > 0 && (
              <button
                onClick={handleMergeAllSimilar}
                disabled={batchMerging}
                className="px-4 py-2.5 rounded-xl bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 disabled:opacity-50 text-white font-semibold text-xs flex items-center justify-center gap-2 shadow-lg shadow-purple-500/25 shrink-0 transition-all"
              >
                {batchMerging ? (
                  <>
                    <div className="w-3.5 h-3.5 border-2 border-white/30 border-t-white rounded-full animate-spin"></div>
                    <span>Consolidating Entire Catalog...</span>
                  </>
                ) : (
                  <>
                    <GitMerge size={15} />
                    <span>Merge All {similarGroups.length} Groups (1-Click)</span>
                  </>
                )}
              </button>
            )}
          </div>

          {loading ? (
            <div className="p-16 text-center text-slate-400 text-xs flex flex-col items-center justify-center">
              <div className="w-7 h-7 border-2 border-purple-500 border-t-transparent rounded-full animate-spin mb-2"></div>
              Scanning database for similar artist names across all users...
            </div>
          ) : similarGroups.length === 0 ? (
            <div className="p-16 text-center rounded-2xl bg-slate-900/40 border border-white/5 max-w-xl mx-auto space-y-3">
              <div className="w-12 h-12 rounded-full bg-emerald-500/15 border border-emerald-500/30 flex items-center justify-center mx-auto text-emerald-400">
                <CheckCircle2 size={24} />
              </div>
              <h4 className="text-base font-bold text-white">Artist Catalog is Fully Deduplicated</h4>
              <p className="text-xs text-slate-400">
                No duplicate variations, featured splits, or casing anomalies were detected across your library.
              </p>
            </div>
          ) : (
            <div className="space-y-3">
              {similarGroups.map((group) => (
                <div key={group.key} className="p-4 sm:p-5 rounded-2xl bg-slate-900/70 border border-white/10 space-y-3 shadow-md">
                  <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-white/5 pb-3">
                    <div>
                      <div className="text-[10px] font-bold uppercase tracking-wider text-purple-400 flex items-center gap-1.5 mb-0.5">
                        <CheckCircle2 size={12} /> Recommended Canonical Profile
                      </div>
                      <h3 className="text-base font-bold text-white flex items-center gap-2">
                        <span>{group.target_artist.name}</span>
                        <span className="text-[11px] px-2 py-0.5 rounded-md bg-purple-500/20 text-purple-300 font-mono font-normal">
                          {group.target_artist.song_count || 0} tracks • {group.target_artist.album_count || 0} albums
                        </span>
                      </h3>
                    </div>

                    <button
                      onClick={() => handleMergeGroup(group)}
                      disabled={mergingGroupId === group.key}
                      className="px-3.5 py-2 rounded-xl bg-purple-600 hover:bg-purple-500 disabled:opacity-50 text-white text-xs font-semibold flex items-center gap-1.5 shadow-md shadow-purple-500/20 transition-all self-start sm:self-auto"
                    >
                      {mergingGroupId === group.key ? (
                        <div className="w-3.5 h-3.5 border-2 border-white/30 border-t-white rounded-full animate-spin"></div>
                      ) : (
                        <GitMerge size={14} />
                      )}
                      <span>Merge into &quot;{group.target_artist.name}&quot;</span>
                    </button>
                  </div>

                  <div className="space-y-2">
                    <div className="text-xs text-slate-400 font-medium">
                      Duplicate profile(s) to fold into {group.target_artist.name}:
                    </div>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                      {group.duplicates.map(dup => (
                        <div key={dup.id} className="p-2.5 rounded-xl bg-slate-950 border border-white/5 flex items-center justify-between gap-2">
                          <div className="min-w-0 flex-1">
                            <div className="text-xs font-semibold text-slate-200 truncate">{dup.name}</div>
                            <div className="text-[11px] text-slate-500 flex items-center gap-2 mt-0.5">
                              <span>{dup.song_count || 0} tracks • {dup.album_count || 0} albums</span>
                              <span className="px-1.5 py-0.2 rounded text-[10px] bg-amber-500/15 text-amber-300 border border-amber-500/25 truncate">
                                {dup.reason}
                              </span>
                            </div>
                          </div>
                          <button
                            type="button"
                            onClick={() => handleMergeGroup(group, dup.id)}
                            className="text-[10px] px-2 py-1 rounded-lg bg-white/5 hover:bg-white/10 text-slate-400 hover:text-white transition shrink-0"
                            title="Make this the canonical target artist instead"
                          >
                            Set Primary
                          </button>
                        </div>
                      ))}
                    </div>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      ) : (
        /* Manual Target Artist Selector & Action Controls */
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-5 items-start">
        
        {/* Left Column: Target Artist Selector */}
        <div className="lg:col-span-5 bg-slate-900/70 border border-white/10 rounded-2xl p-4 sm:p-5 space-y-4">
          <div>
            <label className="text-xs font-semibold text-purple-300 uppercase tracking-wider block mb-1.5 flex items-center gap-1.5">
              <Database size={13} />
              <span>Step 1: Choose Target Artist Profile</span>
            </label>
            <p className="text-xs text-slate-400 mb-3">
              All selected tracks, albums, or merged profiles will be mapped to this destination artist.
            </p>

            {/* Target Artist Search Input */}
            <div className="relative mb-2">
              <Search size={14} className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-500" />
              <input 
                type="text"
                placeholder="Search target artist name..."
                value={targetArtistSearch}
                onChange={(e) => setTargetArtistSearch(e.target.value)}
                className="w-full bg-slate-950 border border-white/10 rounded-xl pl-9 pr-3 py-2 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-purple-500 transition-colors"
              />
            </div>

            {/* Target Artist Dropdown */}
            <select
              value={targetArtistId}
              onChange={(e) => setTargetArtistId(e.target.value)}
              className="w-full bg-slate-950 border border-white/15 rounded-xl px-3 py-2.5 text-xs sm:text-sm text-white focus:outline-none focus:border-purple-500 transition-colors"
            >
              <option value="">-- Select Canonical Artist --</option>
              {targetCandidates.map(a => (
                <option key={a.id} value={a.id}>
                  {a.name} ({a.song_count || 0} songs, {a.album_count || 0} albums) [ID: {a.id}]
                </option>
              ))}
            </select>
          </div>

          {/* Selected Target Preview Card */}
          {selectedTargetArtist && (
            <div className="p-3.5 rounded-xl bg-purple-500/10 border border-purple-500/30">
              <div className="text-xs text-purple-300 font-semibold mb-1 flex items-center gap-1">
                <CheckCircle2 size={14} /> Selected Canonical Artist
              </div>
              <div className="text-base font-bold text-white truncate">{selectedTargetArtist.name}</div>
              <div className="text-xs text-slate-400 mt-0.5">
                Current Catalog: {selectedTargetArtist.song_count || 0} tracks • {selectedTargetArtist.album_count || 0} albums • Database ID: {selectedTargetArtist.id}
              </div>
            </div>
          )}

          {/* Action Trigger Card */}
          <div className="pt-2 border-t border-white/5 space-y-2">
            <div className="flex items-center justify-between text-xs text-slate-300 font-medium">
              <span>Items to merge/remap:</span>
              <span className="font-mono text-purple-400 font-bold">{selectedIds.size}</span>
            </div>

            <button
              onClick={handleExecuteMerge}
              disabled={merging || !targetArtistId || selectedIds.size === 0}
              className="w-full py-3 px-4 rounded-xl bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 disabled:opacity-40 disabled:hover:from-purple-600 disabled:hover:to-indigo-600 text-white font-semibold text-xs sm:text-sm flex items-center justify-center gap-2 shadow-lg shadow-purple-500/20 transition-all active:scale-[0.98]"
            >
              {merging ? (
                <>
                  <div className="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></div>
                  <span>Updating Ampache Database...</span>
                </>
              ) : (
                <>
                  <GitMerge size={17} />
                  <span>Execute Merge & Remap</span>
                </>
              )}
            </button>

            {(activeTab === 'songs' || activeTab === 'albums') && (
              <button
                onClick={handleUnlinkFromArtist}
                disabled={unlinking || selectedIds.size === 0}
                className="w-full py-2.5 px-4 rounded-xl bg-slate-800 hover:bg-red-950/40 border border-red-500/20 disabled:opacity-40 text-red-300 font-semibold text-xs sm:text-sm flex items-center justify-center gap-2 transition-all active:scale-[0.98]"
                title="Detach selected items from their current artist profile (routes to Unknown Artist)"
              >
                {unlinking ? (
                  <>
                    <div className="w-4 h-4 border-2 border-red-400/30 border-t-red-300 rounded-full animate-spin"></div>
                    <span>Unlinking...</span>
                  </>
                ) : (
                  <>
                    <Unlink size={15} />
                    <span>Unlink from Artist</span>
                  </>
                )}
              </button>
            )}

            {activeTab === 'songs' && selectedIds.size >= 2 && (
              <button
                onClick={handleOpenMergeSongsModal}
                className="w-full py-2.5 px-4 rounded-xl bg-gradient-to-r from-amber-600 to-orange-600 hover:from-amber-500 hover:to-orange-500 text-white font-semibold text-xs sm:text-sm flex items-center justify-center gap-2 shadow-lg shadow-amber-500/20 transition-all active:scale-[0.98]"
                title="Merge the selected duplicate songs into one canonical track"
              >
                <GitMerge size={15} />
                <span>Merge Songs ({selectedIds.size})</span>
              </button>
            )}
          </div>

          {/* Live Result Feedback */}
          {lastResult && (
            <div className="p-3.5 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-200 text-xs space-y-1 animate-in fade-in">
              <div className="font-bold flex items-center gap-1.5 text-emerald-300">
                <CheckCircle2 size={15} /> Merge Successful!
              </div>
              <p>Target: <strong>{lastResult.target_artist}</strong></p>
              <ul className="list-disc list-inside space-y-0.5 text-emerald-300/90 font-mono">
                <li>Songs remapped: {lastResult.affected_songs}</li>
                <li>Albums remapped: {lastResult.affected_albums}</li>
                {lastResult.merged_artists > 0 && <li>Duplicate artist profiles purged: {lastResult.merged_artists}</li>}
              </ul>
            </div>
          )}
        </div>

        {/* Right Column: Source Item Multi-Selection List */}
        <div className="lg:col-span-7 bg-slate-900/70 border border-white/10 rounded-2xl p-4 sm:p-5 flex flex-col min-h-[480px]">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-3">
            <div>
              <span className="text-xs font-semibold text-purple-300 uppercase tracking-wider block">
                Step 2: Select Items to Move
              </span>
              <p className="text-xs text-slate-400">
                Choose the messy or duplicate {activeTab} you want to reassign.
              </p>
            </div>

            {/* Selection Shortcuts */}
            <div className="flex items-center gap-2">
              <button 
                onClick={selectAll}
                className="text-[11px] px-2.5 py-1 rounded-lg bg-white/5 hover:bg-white/10 text-slate-300 transition"
              >
                Select All
              </button>
              <button 
                onClick={deselectAll}
                className="text-[11px] px-2.5 py-1 rounded-lg bg-white/5 hover:bg-white/10 text-slate-300 transition"
              >
                Clear
              </button>
            </div>
          </div>

          {/* Filter search bar */}
          <div className="relative mb-3">
            <Search size={14} className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-500" />
            <input 
              type="text"
              placeholder={`Filter ${activeTab} by keyword...`}
              value={filterQuery}
              onChange={(e) => setFilterQuery(e.target.value)}
              className="w-full bg-slate-950 border border-white/10 rounded-xl pl-9 pr-3 py-2 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-purple-500 transition-colors"
            />
          </div>

          {/* Item List */}
          <div className="flex-1 overflow-y-auto max-h-[420px] divide-y divide-white/5 border border-white/5 rounded-xl bg-slate-950/60 p-1">
            {loading ? (
              <div className="p-12 text-center text-slate-400 text-xs flex flex-col items-center justify-center">
                <div className="w-6 h-6 border-2 border-purple-500 border-t-transparent rounded-full animate-spin mb-2"></div>
                Loading {activeTab}...
              </div>
            ) : displayedItems.length === 0 ? (
              <div className="p-12 text-center text-slate-500 text-xs">
                No items found matching your filter.
              </div>
            ) : (
              displayedItems.map((item) => {
                const isSelected = selectedIds.has(item.id);
                const isTarget = String(item.id) === String(targetArtistId);

                return (
                  <div
                    key={item.id}
                    onClick={() => {
                      if (!isTarget) toggleSelect(item.id);
                    }}
                    className={`flex items-center justify-between p-2.5 rounded-lg cursor-pointer transition-all ${
                      isTarget 
                        ? 'opacity-40 cursor-not-allowed bg-purple-900/10' 
                        : isSelected 
                          ? 'bg-purple-500/20 border border-purple-500/30 shadow-sm' 
                          : 'hover:bg-white/5'
                    }`}
                  >
                    <div className="flex items-center gap-3 min-w-0 flex-1 pr-2">
                      <button 
                        type="button" 
                        disabled={isTarget}
                        className="text-purple-400 shrink-0"
                      >
                        {isSelected ? <CheckSquare size={16} /> : <Square size={16} className="text-slate-600" />}
                      </button>

                      <div className="min-w-0 flex-1">
                        {editingItemId === item.id ? (
                          <div className="flex items-center gap-1.5" onClick={(e) => e.stopPropagation()}>
                            <input
                              autoFocus
                              value={editingValue}
                              onChange={(e) => setEditingValue(e.target.value)}
                              onKeyDown={(e) => {
                                if (e.key === 'Enter') handleSaveRename(item, e);
                                if (e.key === 'Escape') cancelEditing(e);
                              }}
                              className="flex-1 min-w-0 bg-slate-900 border border-purple-500/40 rounded-lg px-2 py-1 text-xs text-white focus:outline-none focus:border-purple-400"
                            />
                            <button
                              type="button"
                              onClick={(e) => handleSaveRename(item, e)}
                              disabled={savingRename || !editingValue.trim()}
                              className="text-emerald-400 hover:text-emerald-300 disabled:opacity-40 shrink-0"
                              title="Save"
                            >
                              <CheckCircle2 size={15} />
                            </button>
                            <button
                              type="button"
                              onClick={cancelEditing}
                              className="text-slate-500 hover:text-slate-300 shrink-0"
                              title="Cancel"
                            >
                              <X size={15} />
                            </button>
                          </div>
                        ) : (
                          <p className={`text-xs font-semibold truncate flex items-center gap-1.5 group/title ${isSelected ? 'text-purple-300' : 'text-slate-200'}`}>
                            <span className="truncate">{item.name || item.title}</span>
                            {(activeTab === 'songs' || activeTab === 'albums') && (
                              <button
                                type="button"
                                onClick={(e) => startEditing(item, e)}
                                className="opacity-0 group-hover/title:opacity-100 text-slate-500 hover:text-purple-300 shrink-0 transition-opacity"
                                title={`Rename ${activeTab === 'songs' ? 'song title' : 'album name'}`}
                              >
                                <Pencil size={12} />
                              </button>
                            )}
                          </p>
                        )}
                        <p className="text-[11px] text-slate-400 truncate">
                          {activeTab === 'artists' && (
                            <span>{item.song_count || 0} songs • {item.album_count || 0} albums</span>
                          )}
                          {activeTab === 'albums' && (
                            <span>Artist: {item.artist || 'Unknown'} • {item.songCount || 0} songs</span>
                          )}
                          {activeTab === 'songs' && (
                            <span>Artist: {item.artist || 'Unknown'} • Album: {item.album || 'Unknown'}</span>
                          )}
                        </p>
                      </div>
                    </div>

                    <div className="flex items-center gap-2 shrink-0">
                      {isTarget && (
                        <span className="text-[10px] px-2 py-0.5 rounded bg-purple-500/20 text-purple-300 font-semibold">
                          Target
                        </span>
                      )}
                      {(activeTab === 'artists' || activeTab === 'albums') && !isTarget && (
                        <button
                          type="button"
                          onClick={(e) => handleOpenInMerger(item, e)}
                          className="text-[10px] px-2 py-1 rounded-lg bg-purple-500/15 hover:bg-purple-500/25 text-purple-300 font-semibold flex items-center gap-1 transition"
                          title="Set as merge target and jump to Manual Artist Merge"
                        >
                          <GitMerge size={11} />
                          <span className="hidden sm:inline">Open in Merger</span>
                        </button>
                      )}
                      {activeTab === 'songs' && (
                        <button
                          type="button"
                          onClick={(e) => handleDeleteSong(item, e)}
                          disabled={deletingSongId === item.id}
                          className="text-[10px] px-2 py-1 rounded-lg bg-red-500/15 hover:bg-red-500/25 text-red-300 font-semibold flex items-center gap-1 transition disabled:opacity-40"
                          title="Permanently delete this song"
                        >
                          {deletingSongId === item.id ? (
                            <Loader2 size={11} className="animate-spin" />
                          ) : (
                            <Trash2 size={11} />
                          )}
                          <span className="hidden sm:inline">Delete</span>
                        </button>
                      )}
                      <span className="text-[10px] font-mono text-slate-500">ID: {item.id}</span>
                    </div>
                  </div>
                );
              })
            )}
          </div>

          <div className="mt-3 flex items-center justify-between text-[11px] text-slate-400">
            <span>Showing {displayedItems.length} items</span>
            <span className="font-semibold text-purple-400">{selectedIds.size} selected</span>
          </div>

        </div>

      </div>
      )}

      {/* Merge Duplicate Songs Modal — choose which selected track is the
          canonical "Primary" version; the rest are folded into it and removed. */}
      {showMergeSongsModal && (
        <div
          className="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex items-center justify-center p-4"
          onClick={() => !mergingSongs && setShowMergeSongsModal(false)}
        >
          <div
            className="w-full max-w-lg bg-slate-900 border border-white/10 rounded-2xl p-5 sm:p-6 space-y-4 shadow-2xl"
            onClick={(e) => e.stopPropagation()}
          >
            <div className="flex items-center justify-between">
              <h3 className="text-base font-bold text-white flex items-center gap-2">
                <GitMerge size={18} className="text-amber-400" />
                <span>Merge Duplicate Songs</span>
              </h3>
              <button
                onClick={() => setShowMergeSongsModal(false)}
                disabled={mergingSongs}
                className="text-slate-400 hover:text-white disabled:opacity-40"
              >
                <X size={18} />
              </button>
            </div>
            <p className="text-xs text-slate-400">
              Choose the track to keep as the <span className="text-amber-300 font-semibold">Primary</span> version.
              All play history, ratings, favorites, and playlist entries from the other selected tracks will be
              reassigned to it, then the duplicates will be permanently deleted.
            </p>

            <div className="space-y-2 max-h-64 overflow-y-auto">
              {Array.from(selectedIds).map(id => {
                const song = songs.find(s => s.id === id);
                if (!song) return null;
                const isPrimary = String(mergePrimaryId) === String(id);
                return (
                  <label
                    key={id}
                    className={`flex items-center gap-3 p-3 rounded-xl border cursor-pointer transition-all ${
                      isPrimary ? 'bg-amber-500/10 border-amber-500/40' : 'bg-slate-950/60 border-white/5 hover:bg-white/5'
                    }`}
                  >
                    <input
                      type="radio"
                      name="mergePrimarySong"
                      value={id}
                      checked={isPrimary}
                      onChange={() => setMergePrimaryId(String(id))}
                      className="accent-amber-500"
                    />
                    <div className="min-w-0 flex-1">
                      <div className="text-xs font-semibold text-white truncate">{song.title}</div>
                      <div className="text-[11px] text-slate-400 truncate">
                        Artist: {song.artist || 'Unknown'} • Album: {song.album || 'Unknown'} • ID: {song.id}
                      </div>
                    </div>
                    {isPrimary && (
                      <span className="text-[10px] px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-300 font-bold shrink-0">
                        Primary
                      </span>
                    )}
                  </label>
                );
              })}
            </div>

            <div className="flex items-center gap-2 pt-2">
              <button
                onClick={() => setShowMergeSongsModal(false)}
                disabled={mergingSongs}
                className="flex-1 py-2.5 px-4 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold transition-all disabled:opacity-40"
              >
                Cancel
              </button>
              <button
                onClick={handleConfirmMergeSongs}
                disabled={mergingSongs || !mergePrimaryId}
                className="flex-1 py-2.5 px-4 rounded-xl bg-gradient-to-r from-amber-600 to-orange-600 hover:from-amber-500 hover:to-orange-500 disabled:opacity-40 text-white font-semibold text-xs flex items-center justify-center gap-2 shadow-lg shadow-amber-500/20 transition-all active:scale-[0.98]"
              >
                {mergingSongs ? (
                  <>
                    <Loader2 size={14} className="animate-spin" />
                    <span>Merging...</span>
                  </>
                ) : (
                  <>
                    <GitMerge size={14} />
                    <span>Confirm Merge</span>
                  </>
                )}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

