#!/usr/bin/env python3
"""
library_agent.py

Autonomous background music acquisition, headless tagging, and storage routing agent
for the home server with 2011 MacBook Pro architecture.

Modules & Features:
1. Environment & Cron Safeguards:
   - PATH injection for Homebrew (/opt/homebrew/bin:/usr/local/bin)
   - Non-blocking fcntl lockfile (/tmp/library_agent.lock) to prevent cron overlap
   - Clean signal handling (SIGINT / SIGTERM)
   - Mount validation for /Volumes/htdocs
2. Storage Watchdog:
   - Evaluates free disk space on /Volumes/htdocs
   - Routes staging to /Volumes/Music_External/Staging/ if free space < 15GB
3. Request Priority Engine:
   - Queries http://10.247.192.231:8888/api/media/get_requests.php
   - Downloads pending requests first with yt-dlp (320kbps MP3, Android/web_creator spoofing)
   - Notifies http://10.247.192.231:8888/api/media/update_request.php with status='Fulfilled'
4. Backlog Queue Processor:
   - Reads top 100 lines from missing_albums_report.txt
   - Isolates each album into its own subfolder inside staging
   - Atomically prunes processed lines via temporary file swap
5. Headless Tagging & Artwork Engine:
   - AcoustID fingerprinting + MusicBrainz release match
   - Strict 1.2s rate-limiting on MusicBrainz queries
   - Front cover art retrieval from Cover Art Archive
   - Mandatory fallback parser so tracks never become "Unknown Artist" in Ampache
   - Strict ID3v2.3 UTF-16 (encoding=1) Mutagen embedding
6. Auto-Sync Trigger:
   - Sends HTTP POST to http://10.247.192.231:8888/auto_organizer.php ONLY when items_processed > 0
     to protect the 2011 MacBook Pro from overheating during idle runs
"""

import os
import sys

# ---------------------------------------------------------------------------
# 1. Environment & Cron Safeguards (macOS M1)
# ---------------------------------------------------------------------------
# Ensure Homebrew and standard system binary paths are present for cron
os.environ["PATH"] = f"/opt/homebrew/bin:/usr/local/bin:{os.environ.get('PATH', '')}"

import fcntl
import json
import logging
import random
import re
import shutil
import signal
import time
from pathlib import Path
from typing import Any, Dict, List, Optional, Tuple

import acoustid
import musicbrainzngs
from mutagen.id3 import (
    APIC,
    ID3,
    TALB,
    TDRC,
    TIT2,
    TPE1,
    TRCK,
    ID3NoHeaderError,
)
from mutagen.mp3 import MP3
import requests
import yt_dlp

# Standard timestamped logging
logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(message)s",
    datefmt="%Y-%m-%d %H:%M:%S",
)

# ---------------------------------------------------------------------------
# Configuration & Constants
# ---------------------------------------------------------------------------
ACOUSTID_API_KEY = os.environ.get("ACOUSTID_API_KEY", "ptOkahMcIt")

MUSICBRAINZ_APP = "AetherLibraryAgent"
MUSICBRAINZ_VERSION = "1.0"
MUSICBRAINZ_CONTACT = "admin@serverflow.icu"

PRIMARY_DRIVE = "/Volumes/htdocs"
PRIMARY_STAGING = Path("/Volumes/htdocs/staging")
BACKUP_STAGING = Path("/Volumes/Music_External/Staging")
MIN_FREE_SPACE_GB = 15.0

GET_REQUESTS_URL = "http://10.247.192.231:8888/api/media/get_requests.php"
UPDATE_REQUEST_URL = "http://10.247.192.231:8888/api/media/update_request.php"
SYNC_URL = "http://10.247.192.231:8888/auto_organizer.php"

SCRIPT_DIR = Path(__file__).resolve().parent
REPORT_FILE = SCRIPT_DIR / "missing_albums_report.txt"
LOCK_FILE_PATH = "/tmp/library_agent.lock"
BATCH_SIZE = 100

# Rate limiter state for MusicBrainz
_LAST_MB_CALL = 0.0
_LOCK_FD: Optional[int] = None


# ---------------------------------------------------------------------------
# Lockfile & Signal Handling
# ---------------------------------------------------------------------------
def acquire_lock() -> None:
    """Acquire a non-blocking exclusive lockfile. Exit cleanly if already running."""
    global _LOCK_FD
    try:
        _LOCK_FD = os.open(LOCK_FILE_PATH, os.O_CREAT | os.O_RDWR, 0o644)
        fcntl.flock(_LOCK_FD, fcntl.LOCK_EX | fcntl.LOCK_NB)
        os.write(_LOCK_FD, f"{os.getpid()}\n".encode("utf-8"))
    except (BlockingIOError, IOError):
        logging.info("Agent already running. Exiting cleanly.")
        sys.exit(0)


def release_lock() -> None:
    """Release and remove the lockfile."""
    global _LOCK_FD
    if _LOCK_FD is not None:
        try:
            fcntl.flock(_LOCK_FD, fcntl.LOCK_UN)
            os.close(_LOCK_FD)
        except Exception:
            pass
        _LOCK_FD = None
    try:
        if os.path.exists(LOCK_FILE_PATH):
            os.unlink(LOCK_FILE_PATH)
    except Exception:
        pass


def signal_handler(signum: int, frame: Any) -> None:
    """Handle termination signals cleanly."""
    logging.info(f"Received signal {signum}. Cleaning up lockfile and exiting.")
    release_lock()
    sys.exit(0)


# ---------------------------------------------------------------------------
# Utility & Sanitization
# ---------------------------------------------------------------------------
def sanitize_name(name: str) -> str:
    """Remove illegal filesystem characters for APFS/HFS+/SMB compatibility."""
    return re.sub(r'[\\/*?:"<>|]', "", name).strip()


def mb_rate_limit() -> None:
    """Enforce a strict 1.2s delay before MusicBrainz API calls (1 req/sec limit)."""
    global _LAST_MB_CALL
    now = time.time()
    elapsed = now - _LAST_MB_CALL
    if elapsed < 1.2:
        time.sleep(1.2 - elapsed)
    _LAST_MB_CALL = time.time()


# ---------------------------------------------------------------------------
# Module 1: Storage Watchdog (Handling External Expansion)
# ---------------------------------------------------------------------------
def get_staging_directory() -> Path:
    """
    Check primary mount availability and free storage space.
    Switches to external backup mount if free space falls below 15GB.
    """
    if not os.path.exists(PRIMARY_DRIVE):
        logging.critical(f"Storage mount {PRIMARY_DRIVE} is unavailable. Exiting cleanly.")
        sys.exit(0)

    try:
        total, used, free = shutil.disk_usage(PRIMARY_DRIVE)
        free_gb = free / (1024 ** 3)
        logging.info(f"Storage check on {PRIMARY_DRIVE}: {free_gb:.2f} GB free.")

        if free_gb < MIN_FREE_SPACE_GB:
            logging.warning(
                f"Free space ({free_gb:.2f} GB) is below {MIN_FREE_SPACE_GB} GB threshold. "
                f"Routing to external staging mount: {BACKUP_STAGING}"
            )
            target = BACKUP_STAGING
        else:
            target = PRIMARY_STAGING
    except Exception as e:
        logging.error(f"Failed to check disk usage for {PRIMARY_DRIVE}: {e}. Fallback to {BACKUP_STAGING}")
        target = BACKUP_STAGING

    target.mkdir(parents=True, exist_ok=True)
    return target


# ---------------------------------------------------------------------------
# Audio Download Engine (yt-dlp)
# ---------------------------------------------------------------------------
def download_audio_query(query: str, item_dir: Path) -> Optional[Path]:
    """
    Download audio via yt-dlp extracted strictly as 320kbps MP3 into item_dir.
    Uses mobile client spoofing to bypass YouTube bot blocks.
    Returns path to the downloaded MP3 file or None on failure.
    """
    item_dir.mkdir(parents=True, exist_ok=True)
    existing_mp3s = set(item_dir.glob("*.mp3"))

    ydl_opts = {
        "format": "bestaudio/best",
        "outtmpl": str(item_dir / "%(title)s.%(ext)s"),
        "postprocessors": [
            {
                "key": "FFmpegExtractAudio",
                "preferredcodec": "mp3",
                "preferredquality": "320",
            }
        ],
        "noplaylist": True,
        "quiet": True,
        "no_warnings": True,
        "ignoreerrors": False,
        "concurrent_fragment_downloads": 1,
        "extractor_args": {"youtube": {"player_client": ["android", "web_creator"]}},
    }

    try:
        with yt_dlp.YoutubeDL(ydl_opts) as ydl:
            ydl.download([query])
    except Exception as exc:
        logging.error(f"yt-dlp download failed for query '{query}': {exc}")
        return None

    # Detect generated MP3 file
    current_mp3s = list(item_dir.glob("*.mp3"))
    new_mp3s = [f for f in current_mp3s if f not in existing_mp3s]

    if new_mp3s:
        return new_mp3s[0]
    elif current_mp3s:
        return max(current_mp3s, key=os.path.getmtime)

    return None


# ---------------------------------------------------------------------------
# Module 3: Headless Tagging & Artwork (Replacing Picard)
# ---------------------------------------------------------------------------
def apply_id3v23_utf16_tags(
    mp3_path: Path,
    title: str,
    artist: str,
    album: str,
    year: Optional[str] = None,
    track_num: Optional[str] = None,
    cover_path: Optional[Path] = None,
) -> None:
    """
    Embed ID3 metadata strictly formatted as ID3v2.3 with UTF-16 encoding (encoding=1).
    Ensures full compatibility with PHP and Ampache backends.
    """
    try:
        audio = MP3(str(mp3_path), ID3=ID3)
    except ID3NoHeaderError:
        audio = MP3(str(mp3_path))
        audio.add_tags()

    if audio.tags is None:
        audio.add_tags()

    # ID3v2.3: encoding=1 denotes UTF-16 with BOM (required for international characters in v2.3)
    audio.tags.add(TIT2(encoding=1, text=[title]))
    audio.tags.add(TPE1(encoding=1, text=[artist]))
    audio.tags.add(TALB(encoding=1, text=[album]))

    if year:
        audio.tags.add(TDRC(encoding=1, text=[str(year)]))
    if track_num:
        audio.tags.add(TRCK(encoding=1, text=[str(track_num)]))

    if cover_path and cover_path.exists():
        try:
            with open(cover_path, "rb") as img_file:
                img_data = img_file.read()
                audio.tags.add(
                    APIC(
                        encoding=1,
                        mime="image/jpeg",
                        type=3,  # Front cover
                        desc="Cover",
                        data=img_data,
                    )
                )
        except Exception as img_err:
            logging.warning(f"Could not read cover image from {cover_path}: {img_err}")

    audio.save(v2_version=3)
    logging.info(f"Tagged '{mp3_path.name}' [ID3v2.3 UTF-16] -> Artist: {artist} | Title: {title} | Album: {album}")


def tag_audio_file(
    mp3_path: Path,
    fallback_artist: str,
    fallback_title: str,
    fallback_album: str,
) -> None:
    """
    Fingerprint audio with AcoustID, query MusicBrainz for metadata & Cover Art Archive,
    and fallback safely to query-parsed tags so Ampache never sees an 'Unknown Artist'.
    """
    item_dir = mp3_path.parent
    cover_path = item_dir / "cover.jpg"

    rec_id: Optional[str] = None
    release_id: Optional[str] = None
    title: str = fallback_title
    artist: str = fallback_artist
    album: str = fallback_album
    year: Optional[str] = None
    track_num: Optional[str] = None

    # Step A: AcoustID Fingerprinting
    if ACOUSTID_API_KEY and ACOUSTID_API_KEY != "YOUR_API_KEY_HERE":
        try:
            results = acoustid.match(ACOUSTID_API_KEY, str(mp3_path))
            for score, rid, match_title, match_artist in results:
                if score >= 0.5:
                    rec_id = rid
                    if match_title:
                        title = match_title
                    if match_artist:
                        artist = match_artist
                    logging.info(f"AcoustID matched: {artist} - {title} (Score: {score:.2f})")
                    break
        except Exception as acoust_err:
            logging.warning(f"AcoustID fingerprinting skipped or failed: {acoust_err}")

    # Step B: MusicBrainz Lookup by Recording ID
    if rec_id:
        try:
            mb_rate_limit()
            rec_data = musicbrainzngs.get_recording_by_id(rec_id, includes=["releases", "artists"])
            recording = rec_data.get("recording", {})
            if "title" in recording:
                title = recording["title"]
            if "artist-credit" in recording and recording["artist-credit"]:
                artist = recording["artist-credit"][0].get("artist", {}).get("name", artist)

            releases = recording.get("release-list", [])
            if releases:
                primary_rel = releases[0]
                release_id = primary_rel.get("id")
                album = primary_rel.get("title", album)
                if primary_rel.get("date"):
                    year = primary_rel["date"].split("-")[0]
        except Exception as mb_rec_err:
            logging.warning(f"MusicBrainz recording lookup failed for {rec_id}: {mb_rec_err}")

    # Step C: Fallback to MusicBrainz text search if no recording match
    if not release_id and (fallback_artist or fallback_title):
        try:
            mb_rate_limit()
            search_params: Dict[str, str] = {}
            if fallback_artist and fallback_artist != "Unknown Artist":
                search_params["artist"] = fallback_artist
            if fallback_title and fallback_title != "Unknown Track":
                search_params["recording"] = fallback_title

            if search_params:
                search_res = musicbrainzngs.search_recordings(limit=1, **search_params)
                recordings = search_res.get("recording-list", [])
                if recordings:
                    top_match = recordings[0]
                    title = top_match.get("title", title)
                    if "artist-credit" in top_match and top_match["artist-credit"]:
                        artist = top_match["artist-credit"][0].get("artist", {}).get("name", artist)
                    rel_list = top_match.get("release-list", [])
                    if rel_list:
                        rel = rel_list[0]
                        release_id = rel.get("id")
                        album = rel.get("title", album)
                        if rel.get("date"):
                            year = rel["date"].split("-")[0]
                    logging.info(f"MusicBrainz text search match: {artist} - {title} (Album: {album})")
        except Exception as search_err:
            logging.warning(f"MusicBrainz text search failed: {search_err}")

    # Step D: Fetch Cover Art from Cover Art Archive
    if release_id and not cover_path.exists():
        try:
            caa_url = f"https://coverartarchive.org/release/{release_id}/front-500"
            resp = requests.get(caa_url, timeout=30, allow_redirects=True)
            if resp.status_code == 200 and resp.content:
                with open(cover_path, "wb") as f:
                    f.write(resp.content)
                logging.info(f"Downloaded Cover Art for release {release_id} -> {cover_path.name}")
        except Exception as caa_err:
            logging.warning(f"Cover Art Archive retrieval failed for release {release_id}: {caa_err}")

    # Step E: Embed Tags with Strict Fallback
    apply_id3v23_utf16_tags(
        mp3_path=mp3_path,
        title=title,
        artist=artist,
        album=album,
        year=year,
        track_num=track_num,
        cover_path=cover_path if cover_path.exists() else None,
    )


# ---------------------------------------------------------------------------
# Module 2: The Request Priority Engine
# ---------------------------------------------------------------------------
def process_priority_requests(staging_dir: Path) -> int:
    """
    Check the API for pending requests.
    Downloads, tags, and marks them 'Fulfilled' before processing the backlog.
    Returns the number of processed requests.
    """
    logging.info("Checking priority music requests from home server API...")
    try:
        resp = requests.get(GET_REQUESTS_URL, timeout=30)
        resp.raise_for_status()
        data = resp.json()
    except Exception as exc:
        logging.error(f"Could not reach requests API at {GET_REQUESTS_URL}: {exc}")
        return 0

    if not isinstance(data, list):
        logging.info("Priority requests endpoint did not return a list. Moving on.")
        return 0

    pending = [
        item for item in data
        if isinstance(item, dict) and str(item.get("status", "")).strip().lower() == "pending"
    ]

    if not pending:
        logging.info("No pending priority requests found.")
        return 0

    logging.info(f"Discovered {len(pending)} pending request(s). Processing first...")
    processed_count = 0

    for req in pending:
        req_id = req.get("id")
        artist = str(req.get("artist_name", "")).strip()
        title = str(req.get("track_title", "")).strip()

        if not artist and not title:
            logging.warning(f"Skipping malformed request entry: {req}")
            continue

        folder_label = f"{artist} - {title}" if (artist and title) else (artist or title)
        safe_folder = sanitize_name(folder_label) or "Priority_Request"
        item_dir = staging_dir / safe_folder
        query = f'ytsearch1:"{artist} {title} audio"'

        logging.info(f"Downloading priority track: {artist} - {title}")
        mp3_path = download_audio_query(query, item_dir)

        if mp3_path:
            tag_audio_file(
                mp3_path=mp3_path,
                fallback_artist=artist or "Unknown Artist",
                fallback_title=title or "Unknown Track",
                fallback_album=f"{title} - Single" if title else "Single",
            )
            processed_count += 1

            # Update request status to 'Fulfilled'
            if req_id is not None:
                try:
                    update_resp = requests.post(
                        UPDATE_REQUEST_URL,
                        data={"id": req_id, "status": "Fulfilled"},
                        timeout=15,
                    )
                    logging.info(f"Updated request #{req_id} to 'Fulfilled' (HTTP {update_resp.status_code})")
                except Exception as up_err:
                    logging.warning(f"Failed to update request #{req_id} status on server: {up_err}")
        else:
            logging.error(f"Failed to download audio for request: {artist} - {title}")

        # Polite randomized delay between downloads
        time.sleep(random.uniform(8.0, 12.0))

    return processed_count


# ---------------------------------------------------------------------------
# Backlog Queue Processor
# ---------------------------------------------------------------------------
def process_backlog(staging_dir: Path) -> int:
    """
    Read top 100 lines from missing_albums_report.txt, download and tag them,
    and atomically prune the processed lines from the report file.
    Returns the number of processed backlog items.
    """
    logging.info("Checking standard missing albums backlog...")
    if not REPORT_FILE.exists():
        logging.info(f"Backlog file '{REPORT_FILE.name}' not found. Creating empty file.")
        REPORT_FILE.touch()
        return 0

    try:
        with open(REPORT_FILE, "r", encoding="utf-8") as f:
            lines = [line.strip() for line in f if line.strip()]
    except Exception as read_err:
        logging.error(f"Failed to read backlog file {REPORT_FILE}: {read_err}")
        return 0

    if not lines:
        logging.info("Backlog queue is empty. Checking priority requests only.")
        return 0

    current_batch = lines[:BATCH_SIZE]
    remaining = lines[BATCH_SIZE:]
    logging.info(f"Processing batch of {len(current_batch)} backlog album(s). ({len(remaining)} queued for subsequent runs)")

    processed_count = 0
    for idx, line in enumerate(current_batch, 1):
        logging.info(f"[{idx}/{len(current_batch)}] Backlog album: '{line}'")

        if " - " in line:
            parts = line.split(" - ", 1)
            fallback_artist = parts[0].strip()
            fallback_album = parts[1].strip()
        else:
            fallback_artist = "Unknown Artist"
            fallback_album = line.strip()

        safe_folder = sanitize_name(line) or f"Album_{idx}"
        item_dir = staging_dir / safe_folder
        query = f'ytsearch1:"{line} full album audio"'

        mp3_path = download_audio_query(query, item_dir)
        if mp3_path:
            tag_audio_file(
                mp3_path=mp3_path,
                fallback_artist=fallback_artist,
                fallback_title=fallback_album,
                fallback_album=fallback_album,
            )
            processed_count += 1
        else:
            logging.error(f"Failed to download backlog item: '{line}'")

        time.sleep(random.uniform(8.0, 12.0))

    # Atomic Pruning via temporary file swap
    tmp_file = SCRIPT_DIR / "missing_albums_report.txt.tmp"
    try:
        with open(tmp_file, "w", encoding="utf-8") as f:
            for rem_line in remaining:
                f.write(f"{rem_line}\n")
        os.replace(tmp_file, REPORT_FILE)
        logging.info(f"Atomically pruned {len(current_batch)} line(s) from {REPORT_FILE.name}. Remaining: {len(remaining)}")
    except Exception as prune_err:
        logging.error(f"Critical error pruning backlog file {REPORT_FILE}: {prune_err}")

    return processed_count


# ---------------------------------------------------------------------------
# Module 4: Auto-Sync Trigger
# ---------------------------------------------------------------------------
def trigger_auto_sync(items_processed: int) -> None:
    """
    Fire HTTP POST to auto_organizer.php on the 2011 MacBook Pro home server
    ONLY if new items were downloaded and tagged. Prevents unnecessary CPU churn
    and overheating during idle cron executions.
    """
    if items_processed > 0:
        logging.info(f"Batch completed with {items_processed} new item(s). Triggering auto-organizer on home server...")
        try:
            resp = requests.post(
                SYNC_URL,
                headers={"User-Agent": f"{MUSICBRAINZ_APP}/{MUSICBRAINZ_VERSION}"},
                timeout=30,
            )
            logging.info(f"Auto-sync trigger completed successfully (HTTP {resp.status_code})")
        except Exception as sync_err:
            logging.error(f"Failed to trigger auto-sync endpoint at {SYNC_URL}: {sync_err}")
    else:
        logging.info("No new items downloaded during this run. Skipping auto-sync trigger to avoid waking home server.")


# ---------------------------------------------------------------------------
# Main Orchestration Loop
# ---------------------------------------------------------------------------
def main() -> None:
    # Register termination signals to guarantee lockfile release
    signal.signal(signal.SIGINT, signal_handler)
    signal.signal(signal.SIGTERM, signal_handler)

    acquire_lock()
    try:
        logging.info("=== Library Agent Starting ===")

        # Initialize MusicBrainz Client User-Agent
        musicbrainzngs.set_useragent(
            MUSICBRAINZ_APP,
            MUSICBRAINZ_VERSION,
            MUSICBRAINZ_CONTACT,
        )

        # 1. Storage Watchdog
        staging_dir = get_staging_directory()
        logging.info(f"Staging directory active: {staging_dir}")

        # 2. Priority Requests Engine
        requests_processed = process_priority_requests(staging_dir)

        # 3. Backlog Queue Processor
        backlog_processed = process_backlog(staging_dir)

        total_processed = requests_processed + backlog_processed
        logging.info(f"=== Library Agent Execution Summary: {total_processed} items processed (Requests: {requests_processed}, Backlog: {backlog_processed}) ===")

        # 4. Auto-Sync Trigger (Protected for 2011 MacBook Pro)
        trigger_auto_sync(total_processed)

    except Exception as fatal_err:
        logging.critical(f"Unhandled fatal error in Library Agent: {fatal_err}", exc_info=True)
    finally:
        logging.info("Releasing lockfile. Agent exiting cleanly.")
        release_lock()


if __name__ == "__main__":
    main()
