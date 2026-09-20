#!/usr/bin/env python3
"""
scripts/organize_mac2_music.py — Mac2 Music Normalizer & Folder Architecture Tool
Home Server (MacBook Pro 2011) — Ampache v6 Prep Tool

Standardizes raw /Volumes/Music/ (mac2) files into:
  <MusicRoot>/<Artist>/<Album>/<Track# - Title>.<ext>

Features:
- Strips YouTube/web-rip garbage tags ([Official Video], (Lyrics), [1080p], [FLAC], 320kbps, etc.)
- Normalizes track numbers (e.g. '1.' -> '01 - ')
- Reads native ID3/FLAC metadata if mutagen is installed, with intelligent filename/path fallback
- Safe Dry-Run mode by default (use --apply to execute changes)
"""

import os
import sys
import re
import shutil
import argparse
from pathlib import Path

# Supported audio extensions
AUDIO_EXTENSIONS = {'.mp3', '.flac', '.m4a', '.aac', '.alac', '.wav', '.ogg', '.opus', '.aiff'}

# Common rip artifacts to purge from artist, album, and song titles
GARBAGE_PATTERNS = [
    r'\[\s*(official\s*(music\s*)?video|audio|lyrics?|visualizer|hd|hq|1080p|720p|4k|flac|320kbps|explicit)\s*\]',
    r'\(\s*(official\s*(music\s*)?video|audio|lyrics?|visualizer|hd|hq|1080p|720p|4k|flac|320kbps|explicit)\s*\)',
    r'\[\s*free\s*download\s*\]',
    r'\(\s*free\s*download\s*\)',
    r'\[\s*premiere\s*\]',
    r'\(prod\.\s*[^)]+\)',
    r'\[prod\.\s*[^]]+\]',
    r'_+',  # Multiple underscores to spaces
]

def clean_name(name: str) -> str:
    """Sanitize and clean song, album, or artist name."""
    if not name:
        return ""
    result = name
    for pattern in GARBAGE_PATTERNS:
        result = re.sub(pattern, ' ', result, flags=re.IGNORECASE)
    
    # Replace illegal filesystem characters for APFS/HFS+
    result = re.sub(r'[/\\:*?"<>|]', '', result)
    
    # Normalize whitespaces
    result = re.sub(r'\s+', ' ', result).strip()
    return result

def clean_track_number(raw_num) -> str:
    """Standardize track number to two digits (e.g. 1 -> '01')."""
    if not raw_num:
        return ""
    num_str = str(raw_num).split('/')[0].strip()
    match = re.search(r'\b\d+\b', num_str)
    if match:
        val = int(match.group(0))
        if 0 < val < 100:
            return f"{val:02d}"
        return f"{val}"
    return ""

def extract_metadata(file_path: Path):
    """
    Extract Artist, Album, Title, and Track Number.
    Uses mutagen if installed; otherwise parses folder/filename heuristics.
    """
    artist, album, title, track = None, None, None, None

    # 1. Try mutagen ID3/Vorbis/MP4 reader
    try:
        import mutagen
        from mutagen.easyid3 import EasyID3
        
        audio = mutagen.File(str(file_path), easy=True)
        if audio:
            artist = audio.get('artist', [None])[0] or audio.get('albumartist', [None])[0]
            album = audio.get('album', [None])[0]
            title = audio.get('title', [None])[0]
            track = clean_track_number(audio.get('tracknumber', [None])[0])
    except Exception:
        pass

    # 2. Fallback heuristics from file and parent folder names
    stem = file_path.stem
    parent_dir = file_path.parent.name
    grandparent_dir = file_path.parent.parent.name

    # Check track prefix in filename (e.g. "01 - Song", "01. Song", "1. Song")
    track_match = re.match(r'^(\d{1,2})[\.\s\-]+(.+)$', stem)
    if track_match:
        if not track:
            track = f"{int(track_match.group(1)):02d}"
        cleaned_stem = track_match.group(2).strip()
    else:
        cleaned_stem = stem

    # Check for "Artist - Title" in filename
    if " - " in cleaned_stem:
        parts = cleaned_stem.split(" - ", 1)
        if not artist:
            artist = clean_name(parts[0])
        if not title:
            title = clean_name(parts[1])
    else:
        if not title:
            title = clean_name(cleaned_stem)

    # Heuristic for Album & Artist from folder structure: <Artist>/<Album>/<File>
    if not album and parent_dir and parent_dir.lower() not in {'music', 'mac2', 'songs', 'download', 'downloads'}:
        album = clean_name(parent_dir)

    if not artist and grandparent_dir and grandparent_dir.lower() not in {'music', 'mac2', 'volumes', 'media'}:
        artist = clean_name(grandparent_dir)

    # Final defaults
    artist = clean_name(artist) if artist else "Unknown Artist"
    album = clean_name(album) if album else "Singles"
    title = clean_name(title) if title else clean_name(file_path.stem)
    track = track or "00"

    return artist, album, title, track

def organize_music(music_root: str, dry_run: bool = True):
    root_path = Path(music_root).resolve()
    if not root_path.exists():
        print(f"❌ Error: Music path does not exist: {root_path}")
        return

    print(f"{'🔍 [DRY RUN]' if dry_run else '🚀 [APPLYING]'} Organizing music in: {root_path}")
    print("=" * 70)

    total_scanned = 0
    total_planned = 0
    total_already_organized = 0

    for current_dir, dirs, files in os.walk(root_path):
        for f in files:
            ext = os.path.splitext(f)[1].lower()
            if ext not in AUDIO_EXTENSIONS:
                continue

            total_scanned += 1
            src_file = Path(current_dir) / f

            artist, album, title, track = extract_metadata(src_file)

            # Standard destination filename: "01 - Song Title.ext"
            if track and track != "00":
                dest_filename = f"{track} - {title}{ext}"
            else:
                dest_filename = f"{title}{ext}"

            dest_folder = root_path / artist / album
            dest_file = dest_folder / dest_filename

            # If already properly placed
            if src_file.resolve() == dest_file.resolve():
                total_already_organized += 1
                continue

            total_planned += 1
            rel_src = src_file.relative_to(root_path)
            rel_dest = dest_file.relative_to(root_path)

            print(f"🎵 MOVE: '{rel_src}'\n   ↳ TO: '{rel_dest}'")

            if not dry_run:
                dest_folder.mkdir(parents=True, exist_ok=True)
                # Handle collision
                final_dest = dest_file
                counter = 1
                while final_dest.exists() and final_dest.resolve() != src_file.resolve():
                    final_dest = dest_folder / f"{dest_file.stem} ({counter}){ext}"
                    counter += 1

                shutil.move(str(src_file), str(final_dest))

    # Clean up empty directories in apply mode
    if not dry_run:
        for current_dir, dirs, files in os.walk(root_path, topdown=False):
            if current_dir == str(root_path):
                continue
            if not os.listdir(current_dir):
                try:
                    os.rmdir(current_dir)
                    print(f"🗑️  Removed empty directory: {current_dir}")
                except Exception:
                    pass

    print("=" * 70)
    print(f"📊 Summary:")
    print(f"   • Total audio files scanned: {total_scanned}")
    print(f"   • Already correctly organized: {total_already_organized}")
    print(f"   • Files {'planned to move' if dry_run else 'successfully moved'}: {total_planned}")

    if dry_run:
        print("\n💡 This was a DRY RUN. No files were modified.")
        print("   To execute the reorganization, re-run with: python3 scripts/organize_mac2_music.py --apply")

def main():
    parser = argparse.ArgumentParser(description="Bulk-normalize and organize music folders for Ampache on Mac2.")
    parser.add_argument("--path", default="/Volumes/Music", help="Path to music mount (default: /Volumes/Music)")
    parser.add_argument("--apply", action="store_true", help="Execute reorganization (default is dry-run mode)")
    args = parser.parse_args()

    organize_music(music_root=args.path, dry_run=not args.apply)

if __name__ == "__main__":
    main()

