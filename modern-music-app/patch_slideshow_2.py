import re

with open('src/components/FeaturedSlideshow.jsx', 'r') as f:
    content = f.read()

old_logic = """  const featured = useMemo(() => {
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
    const artist = pickForWeek(artistOptions.length ? artistOptions : artists, weekSeed, 3);"""

new_logic = """  const featured = useMemo(() => {
    const isValidArt = (item) => {
      if (!item) return false;
      const art = item.coverArt || item.albumId || item.id;
      if (!art) return false;
      const strArt = String(art).trim();
      if (strArt === '' || strArt === '0' || strArt === 'unknown') return false;
      if (strArt === DEFAULT_COVER_ART) return false;
      return true;
    };

    const validSongs = (songs || []).filter(isValidArt);
    const validAlbums = (albums || []).filter(isValidArt);
    const validArtists = (artists || []).filter(isValidArt);

    const song = pickForWeek(validSongs, weekSeed, 1);
    const albumOptions = validAlbums.filter(album => {
      const isSameAlbum = song?.albumId && album.id && String(song.albumId) === String(album.id);
      const isSameArtist = song?.artistId && album.artistId && String(song.artistId) === String(album.artistId);
      return !isSameAlbum && !isSameArtist;
    });
    const album = pickForWeek(albumOptions.length ? albumOptions : validAlbums, weekSeed, 2);
    const artistOptions = validArtists.filter(artist => (
      !artistMatches(song, artist) &&
      !artistMatches(album, artist)
    ));
    const artist = pickForWeek(artistOptions.length ? artistOptions : validArtists, weekSeed, 3);"""

content = content.replace(old_logic, new_logic)

with open('src/components/FeaturedSlideshow.jsx', 'w') as f:
    f.write(content)
