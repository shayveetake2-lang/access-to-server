import re

with open('src/components/FeaturedSlideshow.jsx', 'r') as f:
    content = f.read()

# Add isValidArt helper inside FeaturedSlideshow or use the arrays passed.
# I will filter the arrays in the useMemo.

filter_logic = """  const isValidArt = (item) => {
    const art = item?.coverArt || item?.albumId || item?.id;
    if (!art) return false;
    const strArt = String(art).trim();
    if (strArt === '' || strArt === '0' || strArt === 'unknown') return false;
    if (strArt === DEFAULT_COVER_ART) return false;
    return true;
  };

  const randomSong = useMemo(() => {
    const validSongs = (songs || []).filter(isValidArt);
    return validSongs.length > 0 ? validSongs[Math.floor(Math.random() * validSongs.length)] : null;
  }, [songs]);

  const randomAlbum = useMemo(() => {
    const validAlbums = (albums || []).filter(isValidArt);
    return validAlbums.length > 0 ? validAlbums[Math.floor(Math.random() * validAlbums.length)] : null;
  }, [albums]);

  const randomArtist = useMemo(() => {
    const validArtists = (artists || []).filter(isValidArt);
    return validArtists.length > 0 ? validArtists[Math.floor(Math.random() * validArtists.length)] : null;
  }, [artists]);"""

content = re.sub(
    r"  const randomSong = useMemo\(\(\) => \{.*?  \}, \[artists\]\);",
    filter_logic,
    content,
    flags=re.DOTALL
)

with open('src/components/FeaturedSlideshow.jsx', 'w') as f:
    f.write(content)
