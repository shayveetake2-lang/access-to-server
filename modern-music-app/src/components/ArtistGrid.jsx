import { Link } from 'react-router-dom';
import { User, UserX } from 'lucide-react';
import { getCoverArtUrl, DEFAULT_COVER_ART } from '../utils/api';

const isUnknownArtist = (artist) => (artist?.name || '').trim().toLowerCase() === 'unknown artist';

/**
 * Renders a responsive grid of artist cards, giving the canonical
 * "Unknown Artist" profile (grouped orphaned/untagged tracks) a distinct,
 * muted card style so it's clearly recognizable among tagged artists.
 */
export function ArtistGrid({ artists = [], user, getAuthParams }) {
  return (
    <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3 sm:gap-4 md:gap-5">
      {artists.map(artist => (
        <ArtistCard key={artist.id} artist={artist} user={user} getAuthParams={getAuthParams} />
      ))}
    </div>
  );
}

export function ArtistCard({ artist, user, getAuthParams }) {
  const primaryGenre = artist.genres && artist.genres.length > 0 ? artist.genres[0] : null;
  const unknown = isUnknownArtist(artist);

  return (
    <Link
      to={`/artists/${artist.id}`}
      className={`group flex flex-col items-center p-3 sm:p-4 rounded-2xl transition-all border backdrop-blur-sm cursor-pointer active:scale-[0.98] shadow-sm hover:shadow-[0_8px_24px_rgba(0,0,0,0.4)] ${
        unknown
          ? 'bg-slate-900/25 hover:bg-slate-800/40 border-dashed border-slate-500/30 hover:border-slate-400/40'
          : 'bg-slate-900/40 hover:bg-slate-800/60 border-white/5 hover:border-purple-500/30'
      }`}
      title={unknown ? 'Untagged and orphaned tracks grouped together' : artist.name}
    >
      <div
        className={`w-20 h-20 sm:w-24 sm:h-24 rounded-full mb-3 flex items-center justify-center shadow-lg group-hover:scale-105 transition-transform overflow-hidden border-2 ${
          unknown
            ? 'bg-slate-700/50 border-slate-500/30 group-hover:border-slate-400/50'
            : 'bg-gradient-to-br from-indigo-500 to-purple-600 border-white/10 group-hover:border-purple-500/40'
        }`}
      >
        {artist.coverArt ? (
          <img
            src={getCoverArtUrl(artist.coverArt, getAuthParams(user))}
            className="w-full h-full object-cover"
            alt={artist.name}
            loading="lazy"
            onError={(e) => {
              if (e.currentTarget.src !== DEFAULT_COVER_ART) {
                e.currentTarget.src = DEFAULT_COVER_ART;
              }
            }}
          />
        ) : unknown ? (
          <UserX size={28} className="text-slate-400/70" />
        ) : (
          <User size={28} className="text-white/50" />
        )}
      </div>
      <h4 className={`font-semibold text-center transition-colors w-full truncate text-xs sm:text-sm ${
        unknown ? 'text-slate-400 group-hover:text-slate-300' : 'text-slate-100 group-hover:text-purple-400'
      }`}>
        {artist.name}
      </h4>
      <div className="flex items-center justify-center gap-1 text-[11px] text-slate-400 mt-0.5 truncate w-full">
        {unknown ? (
          <span>Grouped tracks</span>
        ) : (
          <>
            <span>{artist.albumCount} {artist.albumCount === 1 ? 'Album' : 'Albums'}</span>
            {primaryGenre && (
              <>
                <span>•</span>
                <span className="text-purple-400/80 truncate max-w-[80px]">{primaryGenre}</span>
              </>
            )}
          </>
        )}
      </div>
    </Link>
  );
}

export default ArtistGrid;
