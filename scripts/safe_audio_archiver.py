#!/usr/bin/env python3
"""
safe_audio_archiver.py

Archives royalty-free / Creative Commons audio from a manually curated
allow-list of YouTube URLs (e.g. NCS releases, or local indie artists who
have given explicit permission) to a local network staging drive.

Usage:
    python3 safe_audio_archiver.py

Requirements:
    pip install yt-dlp tqdm
    ffmpeg must be installed and available on PATH (or set FFMPEG_LOCATION below).

Input:
    approved_downloads.txt - one YouTube URL per line. Blank lines and lines
    starting with '#' are ignored. This file is a manual allow-list that YOU
    curate; only add URLs you have verified are royalty-free, Creative
    Commons licensed, or for which you have explicit permission to archive.
"""

import os
import random
import sys
import time
from pathlib import Path

try:
    from tqdm import tqdm
except ImportError:
    print("Missing dependency 'tqdm'. Install with: pip install tqdm", file=sys.stderr)
    sys.exit(1)

try:
    import yt_dlp
except ImportError:
    print("Missing dependency 'yt-dlp'. Install with: pip install yt-dlp", file=sys.stderr)
    sys.exit(1)

# --- Configuration ---------------------------------------------------------

SCRIPT_DIR = Path(__file__).resolve().parent
ALLOW_LIST_FILE = SCRIPT_DIR / "approved_downloads.txt"
STAGING_DIR = Path("/Volumes/htdocs/staging")

# Randomized delay range (seconds) between downloads to avoid hammering YouTube.
MIN_SLEEP_SECONDS = 10
MAX_SLEEP_SECONDS = 15

# Optional: set an explicit path to the ffmpeg binary if it isn't on PATH,
# e.g. "/opt/homebrew/bin/ffmpeg". Leave as None to rely on PATH.
FFMPEG_LOCATION = None


def load_allow_list(path: Path) -> list[str]:
    """Read the manual URL allow-list, skipping blanks and comments."""
    if not path.exists():
        print(
            f"Allow-list file not found: {path}\n"
            f"Create it and add one YouTube URL per line "
            f"(only royalty-free / CC / permitted tracks).",
            file=sys.stderr,
        )
        sys.exit(1)

    urls = []
    for raw_line in path.read_text(encoding="utf-8").splitlines():
        line = raw_line.strip()
        if not line or line.startswith("#"):
            continue
        urls.append(line)
    return urls


def build_ydl_opts(staging_dir: Path) -> dict:
    """Build yt-dlp options: best audio -> mp3 via ffmpeg postprocessor."""
    opts = {
        "format": "bestaudio/best",
        "outtmpl": str(staging_dir / "%(title)s.%(ext)s"),
        "postprocessors": [
            {
                "key": "FFmpegExtractAudio",
                "preferredcodec": "mp3",
                "preferredquality": "192",
            }
        ],
        "noplaylist": True,
        "quiet": True,
        "no_warnings": True,
        "ignoreerrors": False,
        # Be a good citizen: avoid parallel/aggressive fetching.
        "concurrent_fragment_downloads": 1,
        # Impersonate mobile clients to work around bot-detection reload errors.
        "extractor_args": {"youtube": {"player_client": ["android", "web_creator"]}},
    }
    if FFMPEG_LOCATION:
        opts["ffmpeg_location"] = FFMPEG_LOCATION
    return opts


def download_one(url: str, ydl_opts: dict) -> bool:
    """Download and convert a single URL. Returns True on success."""
    try:
        with yt_dlp.YoutubeDL(ydl_opts) as ydl:
            ydl.download([url])
        return True
    except yt_dlp.utils.DownloadError as exc:
        print(f"  [error] Download failed for {url}: {exc}", file=sys.stderr)
    except Exception as exc:  # defensive: don't let one bad URL kill the batch
        print(f"  [error] Unexpected error for {url}: {exc}", file=sys.stderr)
    return False


def main() -> None:
    urls = load_allow_list(ALLOW_LIST_FILE)
    if not urls:
        print(f"No URLs found in {ALLOW_LIST_FILE}. Nothing to do.")
        return

    os.makedirs("/Volumes/htdocs/staging", exist_ok=True)

    ydl_opts = build_ydl_opts(STAGING_DIR)

    print(f"Archiving {len(urls)} track(s) from allow-list -> {STAGING_DIR}")
    succeeded = 0
    failed = 0

    for index, url in enumerate(tqdm(urls, desc="Archiving tracks", unit="track")):
        tqdm.write(f"[{index + 1}/{len(urls)}] {url}")
        if download_one(url, ydl_opts):
            succeeded += 1
        else:
            failed += 1

        # Skip the sleep after the very last item.
        if index < len(urls) - 1:
            delay = random.uniform(MIN_SLEEP_SECONDS, MAX_SLEEP_SECONDS)
            tqdm.write(f"  Sleeping {delay:.1f}s before next download...")
            time.sleep(delay)

    print(f"\nDone. Succeeded: {succeeded}, Failed: {failed}")


if __name__ == "__main__":
    main()
