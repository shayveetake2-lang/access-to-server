import { useEffect, useState, useMemo } from 'react';
import { Play, Shuffle, Globe, User, BookmarkPlus, ListMusic, ChevronRight, MessageSquare, X } from 'lucide-react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { usePlayer } from '../context/PlayerContext';
import { useToast } from '../context/ToastContext';
import { fetchAllAlbums, fetchAlbumDetails, getAmpacheUrl, getCoverArtUrl, DEFAULT_COVER_ART, fetchFeaturedLibrary } from '../utils/api';
import LikedSongs from './LikedSongs';
import RecentlyAdded from './RecentlyAdded';
import FavoritesGrid from './FavoritesGrid';
import FeaturedSlideshow from './FeaturedSlideshow';
import Recommendations from './Recommendations';

const GENRE_CATEGORIES = [
  { id: 'all', label: 'All Genres' },
  { id: 'pop', label: 'Pop', match: ['pop'] },
  { id: 'rap', label: 'Hip-Hop & Rap', match: ['hip', 'rap', 'trap'] },
  { id: 'rnb', label: 'R&B & Soul', match: ['r&b', 'rnb', 'soul', 'urban'] },
  { id: 'electronic', label: 'Electronic & Dance', match: ['electr', 'dance', 'house', 'techno', 'edm', 'club'] },
  { id: 'rock', label: 'Rock & Alternative', match: ['rock', 'metal', 'punk', 'grunge', 'alt'] },
  { id: 'indie', label: 'Indie & Acoustic', match: ['indie', 'folk', 'acoustic'] },
];

export default function Dashboard() {
  const [allAlbums, setAllAlbums] = useState([]);
  const [allArtists, setAllArtists] = useState([]);
  const [featuredSongs, setFeaturedSongs] = useState([]);
  const [publicPlaylists, setPublicPlaylists] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [selectedGenre, setSelectedGenre] = useState('all');
  const [isQuickListening, setIsQuickListening] = useState(false);
  const [playingAlbumId, setPlayingAlbumId] = useState(null);
  const [savingPlaylistId, setSavingPlaylistId] = useState(null);
  
  // Media Request Modal State
  const [isRequestModalOpen, setIsRequestModalOpen] = useState(false);
  const [requestTitle, setRequestTitle] = useState('');
  const [requestArtist, setRequestArtist] = useState('');
  const [requestNotes, setRequestNotes] = useState('');
  const [isSubmittingRequest, setIsSubmittingRequest] = useState(false);

  const { user, getAuthParams } = useAuth();
  const { playQueue } = usePlayer();
  const { showToast } = useToast();
  const navigate = useNavigate();

  useEffect(() => {
    let isMounted = true;

    const fetchData = async () => {
      if (!user) return;
      try {
        setLoading(true);

        // Fetch parallel:
        // 1. Full Catalog Albums (database-proxy first)
        // 2. Playlists (community public playlists)
        // 3. Featured library
        const [albumRes, playlistRes, featuredRes] = await Promise.allSettled([
          fetchAllAlbums(user),
          fetch(getAmpacheUrl(`action=getPlaylists&${getAuthParams(user)}`)),
          fetchFeaturedLibrary(user)
        ]);

        if (!isMounted) return;

        // Process Library Albums
        let loadedAlbums = [];
        if (albumRes.status === 'fulfilled' && albumRes.value?.albums) {
          loadedAlbums = albumRes.value.albums.filter(album => album.hasArt === true || album.hasArt === 1 || album.hasArt === '1');
        }

        // Process Public Playlists
        let loadedPlaylists = [];
        if (playlistRes.status === 'fulfilled') {
          try {
            const playlistData = await playlistRes.value.json();
            if (playlistData?.['subsonic-response']?.status === 'ok') {
              const rawPl = playlistData['subsonic-response'].playlists?.playlist;
              const arr = Array.isArray(rawPl) ? rawPl : (rawPl ? [rawPl] : []);
              loadedPlaylists = arr.filter(p => 
                p &&
                p.owner !== 'System' && 
                (p.id ? !String(p.id).startsWith('400000') : true) && 
                (p.public === 'true' || p.public === true)
              );
            }
          } catch (pe) {
            console.debug("Public playlists fetch note:", pe);
          }
        }

        setAllAlbums(loadedAlbums);
        setPublicPlaylists(loadedPlaylists);
        if (featuredRes.status === 'fulfilled') {
          setFeaturedSongs(featuredRes.value.songs);
          setAllArtists(featuredRes.value.artists);
        }
      } catch (err) {
        console.error("Dashboard fetch error:", err);
        setError("Could not load music catalog.");
      } finally {
        if (isMounted) {
          setLoading(false);
        }
      }
    };

    fetchData();

    return () => {
      isMounted = false;
    };
  }, [user]);

  // Group albums by genre categories
  const genreSections = useMemo(() => {
    const list = [];
    for (const cat of GENRE_CATEGORIES) {
      if (cat.id === 'all') continue;
      const matchedAlbums = allAlbums.filter(album => {
        if (!album.genre) return false;
        const g = album.genre.toLowerCase();
        return cat.match.some(m => g.includes(m));
      });

      // Sort by play count descending
      matchedAlbums.sort((a, b) => (b.playCount || 0) - (a.playCount || 0));

      if (matchedAlbums.length > 0) {
        list.push({
          ...cat,
          albums: matchedAlbums
        });
      }
    }

    // Fallback: Group uncategorized albums
    const uncategorized = allAlbums.filter(album => {
      if (!album.genre) return true;
      const g = album.genre.toLowerCase();
      return !GENRE_CATEGORIES.some(cat => cat.id !== 'all' && cat.match.some(m => g.includes(m)));
    });

    if (uncategorized.length > 0) {
      uncategorized.sort((a, b) => (b.playCount || 0) - (a.playCount || 0));
      list.push({
        id: 'featured',
        label: 'Trending & Essential Albums',
        albums: uncategorized
      });
    }

    return list;
  }, [allAlbums]);

  const handleSubmitRequest = async (e) => {
    e.preventDefault();
    if (!requestTitle.trim()) return;
    setIsSubmittingRequest(true);
    
    try {
      const data = await submitSongRequest({ title: requestTitle, artist: requestArtist, notes: requestNotes }, user);
      if (data?.status === 'success') {
        showToast(data.message || 'Request submitted successfully!', 'success');
        setIsRequestModalOpen(false);
        setRequestTitle('');
        setRequestArtist('');
        setRequestNotes('');
      } else {
        showToast(data?.message || 'Failed to submit request', 'error');
      }
    } catch (error) {
      showToast('Network error while submitting request', 'error');
    } finally {
      setIsSubmittingRequest(false);
    }
  };

  // Quick Listen: Instant random playback
  const handleQuickListen = async () => {
    if (isQuickListening) return;
    setIsQuickListening(true);
    try {
      const res = await fetch(getAmpacheUrl(`action=getRandomSongs&size=50&${getAuthParams(user)}`));
      const data = await res.json();
      if (data?.['subsonic-response']?.status === 'ok') {
        const raw = data['subsonic-response']?.randomSongs?.song || [];
        const songs = Array.isArray(raw) ? raw : [raw];
        if (songs.length > 0) {
          playQueue(songs, 0);
          showToast(`🎲 Quick Listen: Playing "${songs[0].title}" by ${songs[0].artist}`, 'success');
        } else {
          showToast('No tracks available for Quick Listen', 'warning');
        }
      }
    } catch (err) {
      showToast('Quick Listen failed to start', 'error');
    } finally {
      setIsQuickListening(false);
    }
  };

  // Play Album immediately
  const handlePlayAlbum = async (album, e) => {
    if (e) {
      e.preventDefault();
      e.stopPropagation();
    }
    if (playingAlbumId) return;
    setPlayingAlbumId(album.id);

    try {
      const albumData = await fetchAlbumDetails(album.id, user);
      if (albumData?.song) {
        const rawSongs = albumData.song;
        const songs = Array.isArray(rawSongs) ? rawSongs : [rawSongs];
        if (songs.length > 0) {
          playQueue(songs, 0);
          showToast(`▶ Playing album "${album.name || album.title}"`, 'success');
        } else {
          showToast(`No tracks in "${album.name || album.title}"`, 'warning');
        }
      } else {
        showToast(`No tracks in "${album.name || album.title}"`, 'warning');
      }
    } catch (err) {
      showToast('Failed to play album', 'error');
    } finally {
      setPlayingAlbumId(null);
    }
  };

  // Save public playlist to personal library
  const handleSavePublicPlaylist = async (playlist, e) => {
    if (e) {
      e.preventDefault();
      e.stopPropagation();
    }
    if (savingPlaylistId) return;
    setSavingPlaylistId(playlist.id);

    try {
      const resTracks = await fetch(getAmpacheUrl(`action=getPlaylist&id=${playlist.id}&${getAuthParams(user)}`));
      const dataTracks = await resTracks.json();
      const rawEntries = dataTracks?.['subsonic-response']?.playlist?.entry || [];
      const tracks = Array.isArray(rawEntries) ? rawEntries : (rawEntries ? [rawEntries] : []);

      if (tracks.length === 0) {
        showToast('This playlist is empty', 'warning');
        return;
      }

      const copyName = `${playlist.name} (Saved)`;
      const songIds = tracks.map(t => t.id);
      const dataCreate = await createSubsonicPlaylist(copyName, songIds, false, user);
      
      if (dataCreate?.['subsonic-response']?.status === 'ok') {
        // Tracks are already added during createSubsonicPlaylist
        showToast(`Saved "${playlist.name}" to My Playlists!`, 'success');
      } else {
        showToast('Failed to save playlist', 'error');
      }
    } catch (err) {
      showToast('Network error saving playlist', 'error');
    } finally {
      setSavingPlaylistId(null);
    }
  };

  // Play public playlist directly
  const handlePlayPublicPlaylist = async (playlist, e) => {
    if (e) {
      e.preventDefault();
      e.stopPropagation();
    }
    try {
      const res = await fetch(getAmpacheUrl(`action=getPlaylist&id=${playlist.id}&${getAuthParams(user)}`));
      const data = await res.json();
      if (data?.['subsonic-response']?.status === 'ok') {
        const rawEntries = data['subsonic-response']?.playlist?.entry;
        const tracks = Array.isArray(rawEntries) ? rawEntries : (rawEntries ? [rawEntries] : []);
        if (tracks.length > 0) {
          playQueue(tracks, 0);
          showToast(`▶ Playing "${playlist.name}"`, 'success');
        } else {
          showToast(`Playlist is empty`, 'warning');
        }
      }
    } catch (err) {
      showToast('Failed to play playlist', 'error');
    }
  };

  return (
    <div className="space-y-7 sm:space-y-10 pb-28 max-w-7xl mx-auto">
      
      {/* Top Welcome Bar with Quick Listen Action */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 px-1">
        <div>
          <h1 className="text-xl sm:text-3xl font-extrabold text-white tracking-tight flex items-center gap-2">
            <span>Welcome to Aether</span>
            <span className="hidden sm:inline-block w-2 h-2 rounded-full bg-purple-500 animate-pulse"></span>
          </h1>
          <p className="text-xs sm:text-sm text-slate-400">
            Stream high-fidelity music, explore curated albums, and discover community playlists.
          </p>
        </div>

        {/* Quick Listen Button */}
        <div className="flex items-center gap-2 shrink-0">
          <button
            onClick={handleQuickListen}
            disabled={isQuickListening}
            className="px-4 py-2.5 sm:px-5 sm:py-2.5 rounded-full bg-gradient-to-r from-purple-600 via-indigo-600 to-purple-500 hover:from-purple-500 hover:to-indigo-500 text-white font-semibold text-xs sm:text-sm flex items-center gap-2 shadow-[0_0_24px_rgba(168,85,247,0.45)] hover:shadow-[0_0_32px_rgba(168,85,247,0.65)] transition-all active:scale-95 disabled:opacity-50"
            title="Play a random track instantly"
          >
            {isQuickListening ? (
              <div className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
            ) : (
              <Shuffle size={16} className="animate-spin-slow" />
            )}
            <span>Quick Listen</span>
          </button>
        </div>
      </div>

      {loading ? (
        <div className="flex flex-col items-center justify-center py-24 gap-3">
          <div className="w-10 h-10 border-4 border-purple-500 border-t-transparent rounded-full animate-spin"></div>
          <span className="text-xs text-slate-400 font-medium tracking-wider uppercase">Loading Music Library...</span>
        </div>
      ) : error ? (
        <div className="text-red-400 bg-red-400/10 p-5 rounded-2xl border border-red-400/20 text-sm">{error}</div>
      ) : (
        <>
          <FeaturedSlideshow songs={featuredSongs} albums={allAlbums} artists={allArtists} />

          {/* ✨ Discovery / Recommendations */}
          <Recommendations />

          {/* ❤️ Liked Songs — pinned below featured music */}
          <LikedSongs />

          {/* ⭐ Favorite Albums & Artists — pinned directly below Liked Songs */}
          <FavoritesGrid />

          {/* 🕒 Recently Added Section (New Releases & New Songs) */}
          <RecentlyAdded />

          {/* 🌐 Top Public Community Playlists Showcase */}
          {publicPlaylists.length > 0 && (
            <section className="space-y-4 pt-1">
              <div className="flex items-center justify-between px-1">
                <div>
                  <h3 className="text-lg sm:text-2xl font-bold text-white flex items-center gap-2">
                    <Globe size={20} className="text-purple-400" />
                    <span>Community Playlists</span>
                  </h3>
                  <p className="text-xs text-slate-400 mt-0.5">
                    Curated mixes made public by other listeners on the server
                  </p>
                </div>
                <Link 
                  to="/public-playlists" 
                  className="text-xs font-semibold text-purple-400 hover:text-purple-300 flex items-center gap-1 group transition-colors"
                >
                  <span>See all</span>
                  <ChevronRight size={14} className="group-hover:translate-x-0.5 transition-transform" />
                </Link>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3.5 sm:gap-4 md:gap-5">
                {publicPlaylists.slice(0, 8).map(playlist => {
                  const isOwner = user && (playlist.owner === user.username);
                  return (
                    <div 
                      key={playlist.id}
                      onClick={() => navigate(`/playlists/${playlist.id}`)}
                      className="group flex flex-col bg-slate-900/40 hover:bg-slate-800/60 p-3.5 sm:p-4 rounded-2xl transition-all border border-white/5 hover:border-purple-500/30 backdrop-blur-sm relative active:scale-[0.98] cursor-pointer"
                    >
                      {/* Artwork Banner */}
                      <div className="relative aspect-video rounded-xl overflow-hidden mb-3 shadow-md bg-gradient-to-br from-indigo-950 via-slate-900 to-purple-950 flex items-center justify-center border border-white/5">
                        <ListMusic size={32} className="text-purple-400/40 group-hover:text-purple-300/60 transition-colors" />
                        
                        <div className="hidden sm:flex absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity items-center justify-center">
                          <button 
                            onClick={(e) => handlePlayPublicPlaylist(playlist, e)}
                            className="w-10 h-10 rounded-full bg-purple-500 flex items-center justify-center text-white shadow-xl transform translate-y-2 group-hover:translate-y-0 transition-all duration-200 hover:scale-105 active:scale-95"
                            title="Play Playlist"
                            aria-label="Play Playlist"
                          >
                            <Play fill="currentColor" size={16} className="ml-0.5" />
                          </button>
                        </div>

                        <div className="absolute top-2 left-2 px-1.5 py-0.5 rounded bg-black/60 backdrop-blur-md border border-white/10 text-[9px] font-semibold text-purple-300 flex items-center gap-1">
                          <Globe size={10} />
                          <span>Public</span>
                        </div>
                      </div>

                      <h4 className="font-bold text-slate-100 group-hover:text-purple-300 transition-colors text-sm truncate mb-0.5">
                        {playlist.name}
                      </h4>

                      <div className="flex items-center justify-between text-xs text-slate-400 mt-1">
                        <span className="flex items-center gap-1 truncate text-[11px]">
                          <User size={12} className="text-purple-400 shrink-0" />
                          <span className="truncate">@{playlist.owner || 'Community'}</span>
                        </span>
                        
                        {!isOwner && (
                          <button 
                            onClick={(e) => handleSavePublicPlaylist(playlist, e)}
                            disabled={savingPlaylistId === playlist.id}
                            className="px-2 py-0.5 rounded bg-white/5 hover:bg-purple-500/20 text-slate-300 hover:text-purple-300 transition-colors flex items-center gap-1 text-[10px] font-medium border border-white/5 active:scale-95 shrink-0"
                            title="Save to My Playlists"
                          >
                            <BookmarkPlus size={11} />
                            <span>Save</span>
                          </button>
                        )}
                      </div>
                    </div>
                  );
                })}
              </div>
            </section>
          )}

          {/* Genre Filter Pills */}
          <div className="flex items-center gap-2 overflow-x-auto pb-1 touch-scroll scrollbar-none px-1">
            {GENRE_CATEGORIES.map(cat => {
              const isActive = selectedGenre === cat.id;
              return (
                <button
                  key={cat.id}
                  onClick={() => setSelectedGenre(cat.id)}
                  className={`px-3.5 py-1.5 rounded-full text-xs font-semibold whitespace-nowrap transition-all shrink-0 ${
                    isActive
                      ? 'bg-purple-500 text-white shadow-[0_0_16px_rgba(168,85,247,0.4)]'
                      : 'bg-slate-900/60 hover:bg-slate-800 text-slate-400 hover:text-white border border-white/5'
                  }`}
                >
                  {cat.label}
                </button>
              );
            })}
          </div>

          {/* Top Albums of Each Genre Carousels */}
          <div className="space-y-8 sm:space-y-10">
            {genreSections
              .filter(sec => selectedGenre === 'all' || sec.id === selectedGenre)
              .map(section => (
                <section key={section.id} className="space-y-3.5">
                  <div className="flex items-center justify-between px-1">
                    <div>
                      <h3 className="text-lg sm:text-2xl font-bold text-white flex items-center gap-2">
                        <span>{section.label}</span>
                        <span className="text-xs px-2 py-0.5 rounded-full bg-purple-500/15 text-purple-300 font-medium">
                          {section.albums.length}
                        </span>
                      </h3>
                    </div>
                    <Link 
                      to="/albums" 
                      className="text-xs font-semibold text-purple-400 hover:text-purple-300 flex items-center gap-1 group transition-colors"
                    >
                      <span>Explore all</span>
                      <ChevronRight size={14} className="group-hover:translate-x-0.5 transition-transform" />
                    </Link>
                  </div>

                  {/* Horizontal Scrollable Carousel */}
                  <div className="flex gap-3.5 sm:gap-5 overflow-x-auto pb-3 pt-1 touch-scroll scrollbar-none px-1">
                    {section.albums.slice(0, 12).map(album => (
                      <div 
                        key={album.id}
                        className="group flex flex-col w-36 sm:w-44 md:w-48 shrink-0 bg-slate-900/40 hover:bg-slate-800/60 p-2.5 sm:p-3 rounded-2xl transition-all border border-white/5 hover:border-purple-500/30 backdrop-blur-sm relative active:scale-[0.98] cursor-pointer"
                        onClick={() => navigate(`/albums/${album.id}`)}
                      >
                        {/* Square Cover Art */}
                        <div className="relative aspect-square rounded-xl overflow-hidden mb-2.5 shadow-md bg-slate-800 border border-white/5">
                          <img 
                            src={getCoverArtUrl(album.coverArt || album.id, getAuthParams(user))} 
                            alt={album.name || album.title}
                            className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                            loading="lazy"
                            decoding="async"
                            onError={(e) => { if (e.currentTarget.src !== DEFAULT_COVER_ART) { e.currentTarget.src = DEFAULT_COVER_ART; } }}
/>
                          {/* Quick Play Overlay */}
                          <div className="hidden sm:flex absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity items-center justify-center">
                            <button 
                              onClick={(e) => handlePlayAlbum(album, e)}
                              disabled={playingAlbumId === album.id}
                              className="w-11 h-11 rounded-full bg-purple-500 flex items-center justify-center text-white shadow-xl transform translate-y-2 group-hover:translate-y-0 transition-all duration-200 hover:scale-105 active:scale-95"
                              title="Play Album"
                              aria-label="Play Album"
                            >
                              {playingAlbumId === album.id ? (
                                <div className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                              ) : (
                                <Play fill="currentColor" size={18} className="ml-0.5" />
                              )}
                            </button>
                          </div>
                        </div>

                        {/* Title & Artist */}
                        <div className="px-0.5 min-w-0">
                          <h4 className="font-semibold text-slate-100 group-hover:text-purple-300 transition-colors text-xs sm:text-sm truncate leading-snug">
                            {album.name || album.title}
                          </h4>
                          <p className="text-[11px] sm:text-xs text-slate-400 truncate mt-0.5">
                            {album.artist}
                          </p>
                        </div>
                      </div>
                    ))}
                  </div>
                </section>
              ))}
          </div>
        </>
      )}

      {/* Floating "Request a Song" Button */}
      <button
        onClick={() => setIsRequestModalOpen(true)}
        className="fixed bottom-44 right-4 md:bottom-32 md:right-8 w-14 h-14 rounded-full bg-purple-600 hover:bg-purple-500 text-white shadow-2xl flex items-center justify-center transition-all hover:scale-105 active:scale-95 z-40 border border-purple-400/30"
        title="Request a Song"
      >
        <MessageSquare size={24} />
      </button>

      {/* "Request a Song" Modal */}
      {isRequestModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
          <div className="absolute inset-0 bg-black/60 backdrop-blur-sm" onClick={() => setIsRequestModalOpen(false)}></div>
          <div className="relative bg-slate-900 border border-white/10 rounded-2xl w-full max-w-md shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
            <div className="p-5 border-b border-white/5 flex items-center justify-between">
              <h2 className="text-xl font-bold text-white flex items-center gap-2">
                <MessageSquare className="text-purple-400" size={20} />
                Request a Song
              </h2>
              <button 
                onClick={() => setIsRequestModalOpen(false)}
                className="text-slate-400 hover:text-white transition-colors p-1"
              >
                <X size={20} />
              </button>
            </div>
            <form onSubmit={handleSubmitRequest} className="p-5 space-y-4">
              <div>
                <label className="block text-xs font-medium text-slate-400 mb-1.5">Song Title <span className="text-red-400">*</span></label>
                <input 
                  type="text" 
                  value={requestTitle}
                  onChange={e => setRequestTitle(e.target.value)}
                  className="w-full bg-slate-950/50 border border-white/10 rounded-xl px-4 py-2.5 text-white text-sm focus:outline-none focus:border-purple-500/50 focus:ring-1 focus:ring-purple-500/50"
                  placeholder="e.g. Blinding Lights"
                  required
                />
              </div>
              <div>
                <label className="block text-xs font-medium text-slate-400 mb-1.5">Artist (Optional)</label>
                <input 
                  type="text" 
                  value={requestArtist}
                  onChange={e => setRequestArtist(e.target.value)}
                  className="w-full bg-slate-950/50 border border-white/10 rounded-xl px-4 py-2.5 text-white text-sm focus:outline-none focus:border-purple-500/50 focus:ring-1 focus:ring-purple-500/50"
                  placeholder="e.g. The Weeknd"
                />
              </div>
              <div>
                <label className="block text-xs font-medium text-slate-400 mb-1.5">Additional Notes (Optional)</label>
                <textarea 
                  value={requestNotes}
                  onChange={e => setRequestNotes(e.target.value)}
                  className="w-full bg-slate-950/50 border border-white/10 rounded-xl px-4 py-2.5 text-white text-sm focus:outline-none focus:border-purple-500/50 focus:ring-1 focus:ring-purple-500/50 min-h-[80px]"
                  placeholder="e.g. Please add the explicit version!"
                ></textarea>
              </div>
              <div className="pt-2 flex justify-end gap-3">
                <button
                  type="button"
                  onClick={() => setIsRequestModalOpen(false)}
                  className="px-4 py-2.5 rounded-xl text-sm font-semibold text-slate-300 hover:text-white hover:bg-white/5 transition-colors"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={!requestTitle.trim() || isSubmittingRequest}
                  className="px-5 py-2.5 rounded-xl text-sm font-semibold text-white bg-purple-600 hover:bg-purple-500 transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2 shadow-lg shadow-purple-500/20"
                >
                  {isSubmittingRequest ? (
                    <div className="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></div>
                  ) : null}
                  Submit Request
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
