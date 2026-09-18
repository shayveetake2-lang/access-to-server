import { useAuth } from '../context/AuthContext';
import { useEffect, useState, useMemo } from 'react';
import { useParams, Link } from 'react-router-dom';
import { User, Disc, Play, Shuffle, Clock, Plus, ListPlus, Volume2, ChevronDown, ChevronUp, Sparkles } from 'lucide-react';
import { usePlayer } from '../context/PlayerContext';
import { usePlaylistModal } from '../context/PlaylistModalContext';
import { useToast } from '../context/ToastContext';
import { getCoverArtUrl } from '../utils/api';
import { extractPrimaryArtistName, normalizeArtistKey } from '../utils/artistHelper';

export default function ArtistDetails() {
  const { id } = useParams();
  const [artist, setArtist] = useState(null);
  const [allAlbums, setAllAlbums] = useState([]);
  const [topSongs, setTopSongs] = useState([]);
  const [allSongs, setAllSongs] = useState([]);
  const [loading, setLoading] = useState(true);
  const [loadingTracks, setLoadingTracks] = useState(false);
  const [showAllTopSongs, setShowAllTopSongs] = useState(false);
  const [mergedAliasCount, setMergedAliasCount] = useState(0);

  const { user, getAuthParams } = useAuth();
  const { playQueue, addToQueue, currentTrack, isPlaying } = usePlayer();
  const { openAddToPlaylistModal } = usePlaylistModal();
  const { showToast } = useToast();

  useEffect(() => {
    let isMounted = true;

    const fetchArtistAndDiscography = async () => {
      setLoading(true);
      try {
        const auth = getAuthParams(user);
        // 1. Fetch main artist details
        const res = await fetch(`/ampache/public/rest/index.php?action=getArtist&id=${id}&${auth}`);
        const data = await res.json();

        if (data?.['subsonic-response']?.status !== 'ok' || !data['subsonic-response']?.artist) {
          if (isMounted) {
            setArtist(null);
            setLoading(false);
          }
          return;
        }

        const mainArtist = data['subsonic-response'].artist;
        if (!isMounted) return;
        setArtist(mainArtist);

        const primaryName = extractPrimaryArtistName(mainArtist.name);
        const primaryKey = normalizeArtistKey(primaryName);

        // 2. Discover any featured/similar artist entries in the catalog to merge
        let aliasArtistIds = [];
        try {
          const allArtistsRes = await fetch(`/ampache/public/rest/index.php?action=getArtists&${auth}`);
          const allArtistsData = await allArtistsRes.json();
          if (allArtistsData?.['subsonic-response']?.status === 'ok') {
            const index = allArtistsData['subsonic-response'].artists?.index || [];
            index.forEach(idx => {
              (idx.artist || []).forEach(otherArtist => {
                if (String(otherArtist.id) !== String(id)) {
                  const otherKey = normalizeArtistKey(extractPrimaryArtistName(otherArtist.name));
                  if (otherKey === primaryKey) {
                    aliasArtistIds.push(otherArtist.id);
                  }
                }
              });
            });
          }
        } catch (e) {
          console.debug("Error checking alias artists:", e);
        }

        if (isMounted) setMergedAliasCount(aliasArtistIds.length);

        // 3. Collect albums from main artist + any alias artists
        let combinedAlbums = [...(mainArtist.album || [])];

        if (aliasArtistIds.length > 0) {
          try {
            const aliasFetches = aliasArtistIds.map(aliasId => 
              fetch(`/ampache/public/rest/index.php?action=getArtist&id=${aliasId}&${auth}`)
                .then(r => r.json())
                .catch(() => null)
            );
            const aliasResponses = await Promise.all(aliasFetches);
            aliasResponses.forEach(aliasRes => {
              if (aliasRes?.['subsonic-response']?.status === 'ok' && aliasRes['subsonic-response']?.artist?.album) {
                combinedAlbums = [...combinedAlbums, ...aliasRes['subsonic-response'].artist.album];
              }
            });
          } catch (e) {
            console.debug("Error fetching alias albums:", e);
          }
        }

        // Deduplicate albums by name/title
        const seenAlbumTitles = new Set();
        const uniqueAlbums = combinedAlbums.filter(album => {
          const key = normalizeArtistKey(album.name || '');
          if (!key || seenAlbumTitles.has(key)) return false;
          seenAlbumTitles.add(key);
          return true;
        });

        if (isMounted) setAllAlbums(uniqueAlbums);

        // 4. Fetch Top Songs from getTopSongs endpoint
        let fetchedTopSongs = [];
        try {
          const topRes = await fetch(`/ampache/public/rest/index.php?action=getTopSongs&artist=${encodeURIComponent(mainArtist.name)}&count=15&${auth}`);
          const topData = await topRes.json();
          if (topData?.['subsonic-response']?.status === 'ok' && topData['subsonic-response']?.topSongs?.song) {
            const songs = topData['subsonic-response'].topSongs.song;
            fetchedTopSongs = Array.isArray(songs) ? songs : [songs];
          }
        } catch (e) {
          console.debug("Error fetching top songs:", e);
        }

        // 5. In parallel, fetch album songs to guarantee full song catalog for Shuffle All & fallback
        setLoadingTracks(true);
        let collectedAlbumSongs = [];
        try {
          const albumTracksFetches = uniqueAlbums.map(album =>
            fetch(`/ampache/public/rest/index.php?action=getAlbum&id=${album.id}&${auth}`)
              .then(r => r.json())
              .catch(() => null)
          );
          const albumResponses = await Promise.all(albumTracksFetches);
          albumResponses.forEach(albumRes => {
            if (albumRes?.['subsonic-response']?.status === 'ok' && albumRes['subsonic-response']?.album?.song) {
              const songs = albumRes['subsonic-response'].album.song;
              if (Array.isArray(songs)) {
                collectedAlbumSongs = [...collectedAlbumSongs, ...songs];
              } else if (songs) {
                collectedAlbumSongs.push(songs);
              }
            }
          });
        } catch (e) {
          console.debug("Error fetching album tracks:", e);
        }

        // Deduplicate collected album songs by song id
        const songMap = new Map();
        collectedAlbumSongs.forEach(s => {
          if (s && s.id && !songMap.has(s.id)) {
            songMap.set(s.id, s);
          }
        });
        const allUniqueSongs = Array.from(songMap.values());

        if (isMounted) {
          setAllSongs(allUniqueSongs);

          // If getTopSongs returned tracks, use them; otherwise use the top tracks from albums
          if (fetchedTopSongs.length > 0) {
            setTopSongs(fetchedTopSongs);
          } else {
            // Sort by play count descending if available, else first tracks
            const sorted = [...allUniqueSongs].sort((a, b) => (b.playCount || 0) - (a.playCount || 0));
            setTopSongs(sorted.slice(0, 10));
          }
        }
      } catch (err) {
        console.error("Failed to load artist details:", err);
      } finally {
        if (isMounted) {
          setLoading(false);
          setLoadingTracks(false);
        }
      }
    };

    fetchArtistAndDiscography();

    return () => {
      isMounted = false;
    };
  }, [id, user]);

  // Handler: Play all songs in order
  const handlePlayAll = () => {
    const listToPlay = allSongs.length > 0 ? allSongs : topSongs;
    if (listToPlay.length > 0) {
      playQueue(listToPlay, 0);
      showToast(`Playing all songs by ${artist.name}`, 'success');
    }
  };

  // Handler: Shuffle all songs across all albums
  const handleShuffleAll = () => {
    const listToShuffle = allSongs.length > 0 ? allSongs : topSongs;
    if (listToShuffle.length > 0) {
      const shuffled = [...listToShuffle].sort(() => Math.random() - 0.5);
      playQueue(shuffled, 0);
      showToast(`Shuffling all ${shuffled.length} songs by ${artist.name}`, 'success');
    } else {
      showToast(`No songs available to shuffle for ${artist.name}`, 'info');
    }
  };

  // Handler: Play from Top Songs list
  const handlePlayFromTopSong = (index) => {
    if (topSongs.length > 0) {
      playQueue(topSongs, index);
    }
  };

  if (loading) {
    return (
      <div className="flex justify-center py-24">
        <div className="w-10 h-10 border-4 border-purple-500 border-t-transparent rounded-full animate-spin"></div>
      </div>
    );
  }

  if (!artist) {
    return (
      <div className="text-white text-center py-24">
        <p className="text-slate-400">Artist not found.</p>
        <Link to="/artists" className="mt-4 inline-block text-xs text-purple-400 hover:text-purple-300">
          ← Back to All Artists
        </Link>
      </div>
    );
  }

  const displayedTopSongs = showAllTopSongs ? topSongs : topSongs.slice(0, 5);

  return (
    <div className="pb-28 max-w-7xl mx-auto space-y-8">
      {/* ========================================================================= */}
      {/* ARTIST HERO HEADER                                                        */}
      {/* ========================================================================= */}
      <div className="relative overflow-hidden bg-gradient-to-b from-purple-950/40 via-slate-900/40 to-transparent p-5 sm:p-8 rounded-3xl border border-white/5">
        <div className="flex flex-col sm:flex-row gap-6 sm:gap-8 items-center sm:items-end text-center sm:text-left">
          {/* Circular Artwork */}
          <div className="w-32 h-32 sm:w-40 sm:h-40 md:w-48 md:h-48 rounded-full overflow-hidden shadow-[0_16px_40px_rgba(0,0,0,0.6)] shrink-0 bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center border-4 border-white/10 relative group">
            {artist.coverArt ? (
              <img
                src={`/ampache/public/rest/index.php?action=getCoverArt&id=${artist.coverArt}&${getAuthParams(user)}`}
                className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                alt={artist.name}
              />
            ) : (
              <User size={56} className="text-white/50" />
            )}
          </div>

          {/* Details & Controls */}
          <div className="flex-1 min-w-0">
            <div className="flex items-center justify-center sm:justify-start gap-2 mb-1">
              <span className="text-[11px] sm:text-xs font-semibold uppercase tracking-wider text-purple-400">
                Artist Profile
              </span>
              {mergedAliasCount > 0 && (
                <span className="inline-flex items-center gap-1 text-[10px] text-purple-300 font-medium bg-purple-500/15 px-2 py-0.5 rounded-full border border-purple-500/25">
                  <Sparkles size={10} /> +{mergedAliasCount} featured appearance{mergedAliasCount === 1 ? '' : 's'} merged
                </span>
              )}
            </div>

            <h1 className="text-3xl sm:text-4xl md:text-5xl font-extrabold text-white truncate mb-2 drop-shadow-md">
              {artist.name}
            </h1>

            <div className="flex items-center justify-center sm:justify-start gap-3 text-slate-300 text-xs sm:text-sm font-medium mb-5">
              <span>{allAlbums.length} {allAlbums.length === 1 ? 'Album' : 'Albums'}</span>
              {allSongs.length > 0 && (
                <>
                  <span>•</span>
                  <span>{allSongs.length} Songs</span>
                </>
              )}
            </div>

            {/* Action Buttons: Play All & Shuffle All */}
            <div className="flex items-center justify-center sm:justify-start gap-3 flex-wrap">
              <button
                onClick={handlePlayAll}
                className="bg-purple-500 hover:bg-purple-400 text-white px-5 sm:px-6 py-2.5 rounded-full font-semibold text-xs sm:text-sm flex items-center gap-2 transition-all shadow-[0_0_20px_rgba(168,85,247,0.4)] active:scale-95 shrink-0"
                title="Play all songs in order"
              >
                <Play fill="currentColor" size={16} /> Play All
              </button>

              <button
                onClick={handleShuffleAll}
                className="bg-white/10 hover:bg-white/15 text-white border border-white/10 px-5 sm:px-6 py-2.5 rounded-full font-semibold text-xs sm:text-sm flex items-center gap-2 transition-all active:scale-95 shrink-0 hover:border-purple-500/30 shadow-sm"
                title="Shuffle all songs across all albums"
              >
                <Shuffle size={16} className="text-purple-400" /> Shuffle All Songs
              </button>
            </div>
          </div>
        </div>
      </div>

      {/* ========================================================================= */}
      {/* TOP SONGS / POPULAR TRACKS SECTION                                        */}
      {/* ========================================================================= */}
      {topSongs.length > 0 && (
        <div className="space-y-3">
          <div className="flex items-center justify-between px-1">
            <h2 className="text-lg sm:text-2xl font-bold text-white flex items-center gap-2">
              <Sparkles size={20} className="text-purple-400" /> Top Songs
            </h2>
            {topSongs.length > 5 && (
              <button
                onClick={() => setShowAllTopSongs(!showAllTopSongs)}
                className="text-xs font-semibold text-purple-400 hover:text-purple-300 transition-colors flex items-center gap-1"
              >
                {showAllTopSongs ? (
                  <>Show less <ChevronUp size={14} /></>
                ) : (
                  <>Show all ({topSongs.length}) <ChevronDown size={14} /></>
                )}
              </button>
            )}
          </div>

          {/* Responsive Track Table */}
          <div className="bg-slate-900/40 backdrop-blur-sm rounded-2xl border border-white/5 overflow-hidden">
            <table className="w-full text-left border-collapse table-fixed">
              <thead>
                <tr className="text-slate-400 border-b border-white/10 text-xs uppercase tracking-wider">
                  <th className="font-semibold px-4 sm:px-5 py-3.5 w-12 text-center">#</th>
                  <th className="font-semibold px-4 py-3.5 w-auto">Title</th>
                  <th className="font-semibold px-4 py-3.5 hidden lg:table-cell w-44 xl:w-56">Album</th>
                  <th className="font-semibold px-4 sm:px-6 py-3.5 text-right w-36 sm:w-44 shrink-0">
                    <div className="flex items-center justify-end gap-2 text-slate-400">
                      <Clock size={15} />
                      <span className="text-[11px] normal-case tracking-normal">Actions</span>
                    </div>
                  </th>
                </tr>
              </thead>
              <tbody className="divide-y divide-white/5 text-sm">
                {displayedTopSongs.map((song, index) => {
                  const isCurrent = currentTrack?.id === song.id;
                  return (
                    <tr
                      key={`${song.id}-${index}`}
                      onClick={() => handlePlayFromTopSong(index)}
                      className={`transition-colors group cursor-pointer ${
                        isCurrent ? 'bg-purple-500/15 text-purple-300' : 'text-slate-300 hover:bg-white/5'
                      }`}
                    >
                      {/* Index / Volume Icon */}
                      <td className="px-4 sm:px-5 py-3 text-xs text-slate-400 text-center w-12">
                        {isCurrent ? (
                          <Volume2 size={15} className={`text-purple-400 mx-auto ${isPlaying ? 'animate-pulse' : ''}`} />
                        ) : (
                          <span className="group-hover:hidden">{index + 1}</span>
                        )}
                        {!isCurrent && (
                          <Play size={13} fill="currentColor" className="hidden group-hover:inline-block text-purple-400 mx-auto" />
                        )}
                      </td>

                      {/* Title & Cover */}
                      <td className="px-4 py-3 min-w-0">
                        <div className="flex items-center gap-3 min-w-0">
                          <img
                            src={`/ampache/public/rest/index.php?action=getCoverArt&id=${song.coverArt}&${getAuthParams(user)}`}
                            className="w-10 h-10 rounded-lg bg-slate-800 object-cover shrink-0 border border-white/5 shadow-sm"
                            alt=""
                            loading="lazy"
                          />
                          <div className="min-w-0 flex-1">
                            <div className={`font-medium transition-colors truncate ${
                              isCurrent ? 'text-purple-300 font-semibold' : 'text-white group-hover:text-purple-400'
                            }`}>
                              {song.title}
                            </div>
                            {song.album && (
                              <div className="text-xs text-slate-400 truncate lg:hidden mt-0.5">
                                {song.album}
                              </div>
                            )}
                          </div>
                        </div>
                      </td>

                      {/* Album Column (hidden on narrower views) */}
                      <td className="px-4 py-3 text-slate-400 truncate hidden lg:table-cell">
                        {song.album || 'Unknown Album'}
                      </td>

                      {/* Actions Column (Pinned right, always visible) */}
                      <td className="px-4 sm:px-6 py-3 text-right w-36 sm:w-44 shrink-0">
                        <div className="flex items-center justify-end gap-1.5 font-mono text-xs">
                          {song.duration > 0 && (
                            <span className="text-slate-400 mr-1 text-[11px] sm:text-xs">
                              {Math.floor(song.duration / 60)}:{(song.duration % 60).toString().padStart(2, '0')}
                            </span>
                          )}
                          <button
                            onClick={(e) => {
                              e.stopPropagation();
                              addToQueue(song);
                              showToast(`Added "${song.title}" to play next`, 'success');
                            }}
                            className="w-8 h-8 flex items-center justify-center rounded-lg bg-white/5 hover:bg-purple-500/25 text-slate-300 hover:text-purple-300 active:scale-95 transition-all shadow-sm shrink-0"
                            title="Add to Queue (Play Next)"
                            aria-label="Add to Queue"
                          >
                            <ListPlus size={16} />
                          </button>
                          <button
                            onClick={(e) => {
                              e.stopPropagation();
                              openAddToPlaylistModal(song.id);
                            }}
                            className="w-8 h-8 flex items-center justify-center rounded-lg bg-white/5 hover:bg-purple-500/25 text-slate-300 hover:text-purple-300 active:scale-95 transition-all shadow-sm shrink-0"
                            title="Add to Playlist"
                            aria-label="Add to Playlist"
                          >
                            <Plus size={16} />
                          </button>
                        </div>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* ========================================================================= */}
      {/* ALBUMS GRID                                                               */}
      {/* ========================================================================= */}
      <div className="space-y-4">
        <h2 className="text-lg sm:text-2xl font-bold text-white flex items-center gap-2 px-1">
          <Disc size={20} className="text-purple-400 sm:w-6 sm:h-6" /> Albums & Releases
          <span className="text-xs text-slate-400 font-normal font-mono">({allAlbums.length})</span>
        </h2>

        {allAlbums.length === 0 ? (
          <p className="text-slate-400 text-xs py-8 px-1">No albums found for this artist.</p>
        ) : (
          <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3 sm:gap-4 md:gap-6">
            {allAlbums.map((album) => (
              <Link
                to={`/albums/${album.id}`}
                key={album.id}
                className="group flex flex-col bg-slate-900/40 hover:bg-slate-800/60 p-2 sm:p-3 rounded-2xl transition-all border border-white/5 hover:border-purple-500/30 backdrop-blur-sm active:scale-[0.98] shadow-sm"
              >
                <div className="relative aspect-square rounded-xl overflow-hidden mb-2.5 shadow-md bg-slate-800 border border-white/5">
                  <img
                    src={`/ampache/public/rest/index.php?action=getCoverArt&id=${album.coverArt}&${getAuthParams(user)}`}
                    alt={album.name}
                    className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                    loading="lazy"
                  />
                  <div className="hidden sm:flex absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity items-center justify-center">
                    <button
                      className="w-10 h-10 rounded-full bg-purple-500 flex items-center justify-center text-white shadow-lg transform translate-y-3 group-hover:translate-y-0 transition-all duration-200"
                      aria-label="Play album"
                    >
                      <Play fill="currentColor" size={16} className="ml-0.5" />
                    </button>
                  </div>
                </div>
                <div className="px-1 min-w-0">
                  <h4 className="font-semibold text-slate-100 truncate group-hover:text-purple-400 transition-colors text-xs sm:text-sm leading-snug">
                    {album.name}
                  </h4>
                  <div className="flex items-center gap-1.5 text-[11px] sm:text-xs text-slate-400 truncate mt-0.5">
                    <span>{album.year || 'Unknown Year'}</span>
                    {album.songCount && (
                      <>
                        <span>•</span>
                        <span>{album.songCount} songs</span>
                      </>
                    )}
                  </div>
                </div>
              </Link>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
