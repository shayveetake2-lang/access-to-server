import { useState, useEffect, useMemo } from 'react';
import { 
  Users, Music, Disc, GitMerge, Search, CheckSquare, Square, 
  AlertTriangle, CheckCircle2, RefreshCw, ShieldAlert, ArrowRight,
  Filter, Sparkles, Database
} from 'lucide-react';
import { useAuth } from '../context/AuthContext';
import { useToast } from '../context/ToastContext';
import { getMergeMetadataUrl, getAmpacheUrl } from '../utils/api';

export default function AdminMetadataEditor() {
  const { user, getAuthParams } = useAuth();
  const { showToast } = useToast();

  const [activeTab, setActiveTab] = useState('artists'); // 'artists' | 'albums' | 'songs'
  const [artists, setArtists] = useState([]);
  const [albums, setAlbums] = useState([]);
  const [songs, setSongs] = useState([]);
  const [duplicates, setDuplicates] = useState([]);

  const [selectedIds, setSelectedIds] = useState(new Set());
  const [targetArtistId, setTargetArtistId] = useState('');
  const [targetArtistSearch, setTargetArtistSearch] = useState('');
  const [filterQuery, setFilterQuery] = useState('');

  const [loading, setLoading] = useState(false);
  const [merging, setMerging] = useState(false);
  const [lastResult, setLastResult] = useState(null);

  const isAdmin = user?.role === 'admin' || user?.isAdmin === true || user?.username?.toLowerCase() === 'admin';

  // 1. Fetch initial artists list and potential duplicates
  const fetchMetadata = async () => {
    if (!isAdmin) return;
    setLoading(true);
    try {
      // Fetch artists via Subsonic or merge API
      const auth = getAuthParams(user);
      const resArtists = await fetch(`${getMergeMetadataUrl()}?action=search_artists&limit=150`, {
        headers: { 'Authorization': 'Bearer ' + (localStorage.getItem('auth_token') || '') }
      });
      const dataArtists = await resArtists.json();
      if (dataArtists?.status === 'success') {
        setArtists(dataArtists.artists || []);
      }

      // Fetch duplicate suspects
      try {
        const resDupes = await fetch(`${getMergeMetadataUrl()}?action=get_duplicates`, {
          headers: { 'Authorization': 'Bearer ' + (localStorage.getItem('auth_token') || '') }
        });
        const dataDupes = await resDupes.json();
        if (dataDupes?.status === 'success') {
          setDuplicates(dataDupes.duplicates || []);
        }
      } catch (e) {}

      // Fetch albums for album mode
      if (activeTab === 'albums') {
        const resAlb = await fetch(getAmpacheUrl(`action=getAlbumList2&type=alphabetical&size=100&${auth}`));
        const dataAlb = await resAlb.json();
        const albList = dataAlb?.['subsonic-response']?.albumList2?.album || [];
        setAlbums(Array.isArray(albList) ? albList : [albList]);
      }

      // Fetch songs for song mode
      if (activeTab === 'songs') {
        const resSongs = await fetch(getAmpacheUrl(`action=getRandomSongs&size=100&${auth}`));
        const dataSongs = await resSongs.json();
        const songList = dataSongs?.['subsonic-response']?.randomSongs?.song || [];
        setSongs(Array.isArray(songList) ? songList : [songList]);
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
  }, [activeTab, isAdmin]);

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
      if (!q) return artists;
      return artists.filter(a => a.name && a.name.toLowerCase().includes(q));
    }
    if (activeTab === 'albums') {
      if (!q) return albums;
      return albums.filter(a => 
        (a.title && a.title.toLowerCase().includes(q)) || 
        (a.artist && a.artist.toLowerCase().includes(q))
      );
    }
    if (activeTab === 'songs') {
      if (!q) return songs;
      return songs.filter(s => 
        (s.title && s.title.toLowerCase().includes(q)) || 
        (s.artist && s.artist.toLowerCase().includes(q))
      );
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

  // Execute Merge / Remap
  const handleExecuteMerge = async () => {
    if (!targetArtistId) {
      showToast("Please choose a Target Artist Profile", "warning");
      return;
    }
    if (selectedIds.size === 0) {
      showToast("Please select at least one item to merge or remap", "warning");
      return;
    }

    const targetName = selectedTargetArtist ? selectedTargetArtist.name : `#${targetArtistId}`;
    const confirmMessage = activeTab === 'artists'
      ? `Merge ${selectedIds.size} duplicate artist profile(s) into "${targetName}"?\nAll associated albums and songs will be moved, and duplicate profiles deleted.`
      : `Remap ${selectedIds.size} ${activeTab} to "${targetName}"?`;

    if (!window.confirm(confirmMessage)) return;

    setMerging(true);
    setLastResult(null);

    try {
      const payload = {
        target_artist_id: parseInt(targetArtistId, 10),
        token: localStorage.getItem('auth_token') || sessionStorage.getItem('active_session_token') || '',
        u: user?.username || 'admin'
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
        headers: {
          'Content-Type': 'application/json',
          'Authorization': 'Bearer ' + (localStorage.getItem('auth_token') || '')
        },
        body: JSON.stringify(payload)
      });

      const data = await res.json();

      if (res.ok && data.status === 'success') {
        setLastResult(data);
        showToast(data.message || "Metadata successfully merged!", "success");
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
          onClick={() => setActiveTab('artists')}
          className={`px-4 py-2 rounded-xl text-xs sm:text-sm font-semibold flex items-center gap-2 transition-all ${
            activeTab === 'artists'
              ? 'bg-purple-500 text-white shadow-lg shadow-purple-500/25'
              : 'bg-slate-900/60 text-slate-400 hover:text-white border border-white/5'
          }`}
        >
          <Users size={16} />
          <span>Merge Duplicate Artists</span>
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
      </div>

      {/* Target Artist Selector & Action Controls */}
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
                        <p className={`text-xs font-semibold truncate ${isSelected ? 'text-purple-300' : 'text-slate-200'}`}>
                          {item.name || item.title}
                        </p>
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
    </div>
  );
}

