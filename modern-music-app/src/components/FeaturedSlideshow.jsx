import { useEffect, useMemo, useState } from 'react';
import { ChevronLeft, ChevronRight, Disc3, Mic2, Play, Sparkles } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { usePlayer } from '../context/PlayerContext';
import { getCoverArtUrl } from '../utils/api';

function getWeekSeed(date = new Date()) {
  const thursday = new Date(Date.UTC(date.getUTCFullYear(), date.getUTCMonth(), date.getUTCDate()));
  thursday.setUTCDate(thursday.getUTCDate() + 4 - (thursday.getUTCDay() || 7));
  const isoYear = thursday.getUTCFullYear();
  const firstThursday = new Date(Date.UTC(isoYear, 0, 4));
  const week = 1 + Math.round((thursday - firstThursday) / 86400000 / 7);
  return isoYear * 100 + week;
}

function pickForWeek(items, seed, salt) {
  if (!items.length) return null;
  let value = (seed * 31 + salt * 17) % items.length;
  value = (value + items.length) % items.length;
  return items[value];
}

function sameIdentity(first, second) {
  if (!first || !second) return false;
  const firstId = first.id && String(first.id);
  const secondId = second.id && String(second.id);
  return Boolean(firstId && secondId && firstId === secondId);
}

function artistMatches(item, artist) {
  if (!item || !artist) return false;
  if (sameIdentity(item, artist)) return true;
  return Boolean(item.artistId && artist.id && String(item.artistId) === String(artist.id));
}

export default function FeaturedSlideshow({ songs = [], albums = [], artists = [] }) {
  const [activeSlide, setActiveSlide] = useState(0);
  const [coverFailed, setCoverFailed] = useState(false);
  const navigate = useNavigate();
  const { user, getAuthParams } = useAuth();
  const { playQueue } = usePlayer();
  const weekSeed = useMemo(() => getWeekSeed(), []);

  const featured = useMemo(() => {
    const song = pickForWeek(songs, weekSeed, 1);
    const albumOptions = albums.filter(album => {
      const isSameAlbum = song?.albumId && album.id && String(song.albumId) === String(album.id);
      const isSameArtist = song?.artistId && album.artistId && String(song.artistId) === String(album.artistId);
      return !isSameAlbum && !isSameArtist;
    });
    const album = pickForWeek(albumOptions.length ? albumOptions : albums, weekSeed, 2);
    const artistOptions = artists.filter(artist => (
      !artistMatches(song, artist) &&
      !artistMatches(album, artist)
    ));
    const artist = pickForWeek(artistOptions.length ? artistOptions : artists, weekSeed, 3);

    return [
      { type: 'song', label: 'Song of the Week', item: song, icon: Sparkles },
      { type: 'album', label: 'Album of the Week', item: album, icon: Disc3 },
      { type: 'artist', label: 'Artist of the Week', item: artist, icon: Mic2 }
    ];
  }, [songs, albums, artists, weekSeed]);

  const availableSlides = featured.filter(slide => slide.item);
  const safeActiveSlide = activeSlide < availableSlides.length ? activeSlide : 0;
  const slide = availableSlides[safeActiveSlide] || null;
  const item = slide?.item || null;

  useEffect(() => {
    if (availableSlides.length < 2) return undefined;
    const timer = window.setInterval(() => {
      setActiveSlide(current => (current + 1) % availableSlides.length);
    }, 8000);
    return () => window.clearInterval(timer);
  }, [availableSlides.length]);

  useEffect(() => {
    setCoverFailed(false);
  }, [slide?.type, item?.id]);

  if (!availableSlides.length) return null;

  const Icon = slide.icon;
  const isSong = slide.type === 'song';
  const title = isSong ? item.title : item.name || item.title || 'Untitled';
  const subtitle = slide.type === 'artist'
    ? `${item.albumCount || 0} albums in the library`
    : isSong
      ? `${item.artist || 'Unknown Artist'}${item.album ? ` • ${item.album}` : ''}`
      : item.artist || `${item.songCount || 0} tracks`;
  const coverId = item.coverArt || item.albumId || item.id;

  const initials = title
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map(word => word[0].toUpperCase())
    .join('') || '?';

  const showCoverFallback = !coverId || coverFailed;

  const handlePrimaryAction = () => {
    if (slide.type === 'song') {
      playQueue([item], 0);
    } else if (slide.type === 'album') {
      navigate(`/albums/${item.id}`);
    } else {
      navigate(`/artists/${item.id}`);
    }
  };

  return (
    <section aria-label="Featured music" className="relative overflow-hidden rounded-3xl border border-white/10 bg-slate-950 shadow-[0_16px_40px_rgba(0,0,0,0.55)]">
      <div className="absolute inset-0 bg-gradient-to-br from-purple-950/80 via-slate-950 to-indigo-950/70" />
      <div className="relative z-10 flex min-h-[280px] flex-col gap-6 p-5 sm:min-h-[320px] sm:flex-row sm:items-center sm:p-8">
        <button
          type="button"
          onClick={handlePrimaryAction}
          className="group mx-auto aspect-square w-40 shrink-0 overflow-hidden rounded-2xl border border-white/10 bg-slate-900 shadow-2xl sm:mx-0 sm:w-56"
          aria-label={`Open ${title}`}
        >
          {showCoverFallback ? (
            <div className={`relative flex h-full w-full items-center justify-center bg-gradient-to-br from-slate-800/80 via-slate-900 to-purple-950/60 backdrop-blur-xl ${slide.type === 'artist' ? 'rounded-full' : ''}`}>
              <Disc3 size={40} className="absolute text-white/10" />
              <span className="relative text-2xl font-black tracking-wide text-white/70 sm:text-3xl">{initials}</span>
            </div>
          ) : (
            <img
              src={getCoverArtUrl(coverId, getAuthParams(user))}
              alt=""
              className={`h-full w-full object-cover transition-transform duration-500 group-hover:scale-105 ${slide.type === 'artist' ? 'rounded-full p-2' : ''}`}
              onError={() => setCoverFailed(true)}
            />
          )}
        </button>

        <div className="min-w-0 flex-1 text-center sm:text-left">
          <div className="mb-3 flex items-center justify-center gap-2 text-xs font-bold uppercase tracking-[0.2em] text-purple-300 sm:justify-start">
            <Icon size={16} />
            <span>{slide.label}</span>
          </div>
          <h2 className="truncate text-3xl font-black tracking-tight text-white sm:text-5xl">{title}</h2>
          <p className="mt-2 truncate text-sm text-slate-300 sm:text-base">{subtitle}</p>
          <button
            type="button"
            onClick={handlePrimaryAction}
            className="mt-6 inline-flex items-center gap-2 rounded-full bg-purple-500 px-5 py-2.5 text-sm font-semibold text-white shadow-[0_0_24px_rgba(168,85,247,0.4)] transition hover:bg-purple-400 active:scale-95"
          >
            <Play size={16} fill="currentColor" />
            <span>{isSong ? 'Play Song' : `View ${slide.type === 'album' ? 'Album' : 'Artist'}`}</span>
          </button>
        </div>

        {availableSlides.length > 1 && (
          <div className="absolute bottom-4 right-4 flex items-center gap-1.5 sm:right-8">
            <button type="button" onClick={() => setActiveSlide((safeActiveSlide - 1 + availableSlides.length) % availableSlides.length)} className="flex h-9 w-9 items-center justify-center rounded-full border border-white/10 bg-white/5 text-slate-300 hover:bg-white/10" aria-label="Previous featured item"><ChevronLeft size={18} /></button>
            {availableSlides.map((entry, index) => <button key={entry.type} type="button" onClick={() => setActiveSlide(index)} className={`h-2 rounded-full transition-all ${index === safeActiveSlide ? 'w-6 bg-purple-400' : 'w-2 bg-white/30'}`} aria-label={`Show ${entry.label}`} />)}
            <button type="button" onClick={() => setActiveSlide((safeActiveSlide + 1) % availableSlides.length)} className="flex h-9 w-9 items-center justify-center rounded-full border border-white/10 bg-white/5 text-slate-300 hover:bg-white/10" aria-label="Next featured item"><ChevronRight size={18} /></button>
          </div>
        )}
      </div>
    </section>
  );
}
