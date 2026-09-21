#!/usr/bin/env python3
"""
organize_mac2.py — Recursive music library organizer.

Reads ID3 / VorbisComment / MP4 tags from audio files and moves them into a clean
folder hierarchy:

    <target_dir>/
    └── Music/
        └── <Artist Name>/
            └── <Album Name>/
                └── <TrackNum> - <Title>.ext

Supported formats:  .mp3, .flac, .m4a, .aac, .ogg, .opus, .wma, .wav, .aiff

Usage:
    python organize_mac2.py <source_dir> [--dest <dest_dir>] [--dry-run] [--copy]

Options:
    source_dir      Directory to scan recursively for audio files.
    --dest DIR      Destination root (default: same as source_dir).
    --dry-run       Print what would happen without moving/copying files.
    --copy          Copy files instead of moving them.
    --no-unknown    Skip files whose Artist or Album tags are completely missing.

Requirements:
    pip install mutagen
    (or: python -m pip install mutagen)

Run on Windows:
    py organize_mac2.py D:\\Downloads\\Music --dest D:\\Music --dry-run
"""

import argparse
import re
import shutil
import sys
from pathlib import Path

try:
    from mutagen import File as MutagenFile
    from mutagen.id3 import ID3NoHeaderError
except ImportError:
    print(
        "\n[ERROR] mutagen is not installed.\n"
        "Run:  pip install mutagen\n"
        "  or: py -m pip install mutagen\n",
        file=sys.stderr,
    )
    sys.exit(1)

# ── Constants ─────────────────────────────────────────────────────────────────

AUDIO_EXTENSIONS = {
    ".mp3", ".flac", ".m4a", ".aac", ".ogg",
    ".opus", ".wma", ".wav", ".aiff", ".aif",
}

FORBIDDEN_CHARS_RE = re.compile(r'[<>:"/\\|?*\x00-\x1f]')
MAX_PATH_SEGMENT   = 60  # max chars per folder/filename segment

# ── Helpers ───────────────────────────────────────────────────────────────────

def sanitize(name: str, fallback: str = "Unknown") -> str:
    """Strip filesystem-illegal characters and trim to a safe length."""
    if not name or not name.strip():
        return fallback
    cleaned = FORBIDDEN_CHARS_RE.sub("_", name.strip())
    # Collapse multiple underscores / spaces
    cleaned = re.sub(r"_{2,}", "_", cleaned)
    cleaned = cleaned.strip("._- ")
    return cleaned[:MAX_PATH_SEGMENT] or fallback


def first_tag(audio, *keys) -> str:
    """Return the first non-empty string value from any of the given tag keys."""
    for key in keys:
        val = audio.get(key)
        if val is None:
            continue
        if isinstance(val, list):
            val = val[0] if val else None
        if val is None:
            continue
        s = str(val).strip()
        if s:
            return s
    return ""


def read_tags(path: Path) -> dict:
    """
    Extract Artist, Album, Title, and TrackNumber from an audio file.
    Falls back gracefully when tags are missing or the file is unreadable.
    """
    try:
        audio = MutagenFile(str(path), easy=True)
    except Exception:
        audio = None

    if audio is None:
        return {
            "artist": "",
            "album":  "",
            "title":  path.stem,
            "track":  "",
        }

    # The Easy* wrappers use lowercase keys regardless of format
    artist = first_tag(audio, "artist", "albumartist", "TPE1", "TPE2", "©ART", "Author")
    album  = first_tag(audio, "album",  "©alb", "TALB", "WM/AlbumTitle")
    title  = first_tag(audio, "title",  "©nam", "TIT2", "Title")
    track  = first_tag(audio, "tracknumber", "track", "TRCK", "trkn")

    # Normalise track number: "3/12" → "03"
    if track:
        track = track.split("/")[0].strip().zfill(2)

    return {
        "artist": artist,
        "album":  album,
        "title":  title or path.stem,
        "track":  track,
    }


def build_dest_path(dest_root: Path, tags: dict, src_path: Path) -> Path:
    """
    Construct the full destination path for an audio file given its tags.
    Falls back to 'Unknown Artist' / 'Unknown Album' when tags are absent.
    """
    artist = sanitize(tags["artist"], "Unknown Artist")
    album  = sanitize(tags["album"],  "Unknown Album")
    title  = sanitize(tags["title"],  src_path.stem)
    track  = tags["track"]

    filename = f"{track} - {title}{src_path.suffix.lower()}" if track else f"{title}{src_path.suffix.lower()}"

    return dest_root / "Music" / artist / album / filename


def ensure_unique(path: Path) -> Path:
    """If the destination path already exists, append _2, _3, … until unique."""
    if not path.exists():
        return path
    stem = path.stem
    suffix = path.suffix
    parent = path.parent
    counter = 2
    while True:
        candidate = parent / f"{stem}_{counter}{suffix}"
        if not candidate.exists():
            return candidate
        counter += 1

# ── Main ──────────────────────────────────────────────────────────────────────

def main():
    parser = argparse.ArgumentParser(
        description="Organize audio files into Artist/Album folder hierarchy using ID3 tags.",
        formatter_class=argparse.RawDescriptionHelpFormatter,
    )
    parser.add_argument("source", help="Source directory to scan recursively.")
    parser.add_argument("--dest",        default=None,  help="Destination root directory (default: source).")
    parser.add_argument("--dry-run",     action="store_true", help="Preview changes without touching the filesystem.")
    parser.add_argument("--copy",        action="store_true", help="Copy files instead of moving them.")
    parser.add_argument("--no-unknown",  action="store_true", help="Skip files with no Artist or Album tags.")
    args = parser.parse_args()

    source_root = Path(args.source).resolve()
    dest_root   = Path(args.dest).resolve() if args.dest else source_root
    dry_run     = args.dry_run
    do_copy     = args.copy
    no_unknown  = args.no_unknown

    if not source_root.is_dir():
        print(f"[ERROR] Source directory not found: {source_root}", file=sys.stderr)
        sys.exit(1)

    print(f"\n{'[DRY RUN] ' if dry_run else ''}Scanning: {source_root}")
    print(f"  Destination : {dest_root / 'Music'}")
    print(f"  Mode        : {'copy' if do_copy else 'move'}")
    print()

    files = [
        p for p in source_root.rglob("*")
        if p.is_file() and p.suffix.lower() in AUDIO_EXTENSIONS
    ]

    if not files:
        print("No audio files found.")
        return

    print(f"Found {len(files)} audio file(s).\n")

    moved   = 0
    skipped = 0
    errors  = 0

    for src in sorted(files):
        tags    = read_tags(src)
        no_tags = not tags["artist"] and not tags["album"]

        if no_unknown and no_tags:
            print(f"  [SKIP ] No tags: {src.name}")
            skipped += 1
            continue

        dest = build_dest_path(dest_root, tags, src)

        # Don't overwrite identical file at same path
        if dest.resolve() == src.resolve():
            print(f"  [SKIP ] Already in place: {src.name}")
            skipped += 1
            continue

        dest = ensure_unique(dest)

        verb = "COPY" if do_copy else "MOVE"
        print(f"  [{verb}] {src.relative_to(source_root)}")
        print(f"       → {dest.relative_to(dest_root)}")

        if not dry_run:
            try:
                dest.parent.mkdir(parents=True, exist_ok=True)
                if do_copy:
                    shutil.copy2(str(src), str(dest))
                else:
                    shutil.move(str(src), str(dest))
                moved += 1
            except Exception as exc:
                print(f"  [ERROR] {exc}")
                errors += 1
        else:
            moved += 1

    # Summary
    print()
    print("─" * 50)
    if dry_run:
        print(f"[DRY RUN] Would {'copy' if do_copy else 'move'}: {moved} file(s)")
    else:
        print(f"{'Copied' if do_copy else 'Moved'}: {moved} file(s)")
    if skipped:
        print(f"Skipped : {skipped} file(s)")
    if errors:
        print(f"Errors  : {errors} file(s)")
    print()

    if not dry_run and not do_copy:
        # Clean up empty directories left behind in source
        for dirpath in sorted(source_root.rglob("*"), reverse=True):
            if dirpath.is_dir():
                try:
                    dirpath.rmdir()  # only removes if truly empty
                except OSError:
                    pass
        print("Empty source directories cleaned up.")

    print("Done.")


if __name__ == "__main__":
    main()

