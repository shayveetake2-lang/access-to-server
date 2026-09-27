#!/usr/bin/env python3
"""
lidarr_exporter.py

Audits a local Lidarr instance for missing library items (wanted/missing)
and exports an artist/title report to a local text file for manual review.

NOTE: This intentionally does NOT generate YouTube search strings or write
into approved_downloads.txt. That file is a manually-curated allow-list of
verified royalty-free / Creative Commons / permitted tracks used by
safe_audio_archiver.py. Missing albums from your Lidarr library are your
commercial/mainstream collection gaps, not a royalty-free source list, so
auto-populating a download queue from them would risk bulk-downloading
copyrighted material. Use this report to manually source replacements
(purchase, Bandcamp, official CC releases, etc.).

Configuration (set in a local .env file, never hardcode secrets in this script):
    LIDARR_BASE_URL - defaults to http://localhost:8686 (use with an SSH tunnel
                      when Lidarr runs on a remote host, e.g.
                      ssh -L 8686:192.168.2.4:8686 akshayveerasamy@10.247.192.231)
    LIDARR_API_KEY  - your Lidarr API key, required

Usage:
    python3 lidarr_exporter.py
"""

import os
import sys
from pathlib import Path

import requests
from dotenv import load_dotenv

load_dotenv()

# --- Configuration ---------------------------------------------------------

LIDARR_BASE_URL = os.environ.get("LIDARR_BASE_URL", "http://localhost:8686")
API_KEY = os.environ.get("LIDARR_API_KEY", "")

PAGE_SIZE = 250
SORT_KEY = "title"
SORT_DIRECTION = "descending"

SCRIPT_DIR = Path(__file__).resolve().parent
OUTPUT_FILE = SCRIPT_DIR / "missing_albums_report.txt"

REQUEST_TIMEOUT_SECONDS = 30


def fetch_missing_records(base_url: str, api_key: str) -> list[dict]:
    """Page through /api/v1/wanted/missing until an empty records array."""
    headers = {"X-Api-Key": api_key}
    endpoint = f"{base_url}/api/v1/wanted/missing"

    all_records: list[dict] = []
    page = 1

    while True:
        params = {
            "page": page,
            "pageSize": PAGE_SIZE,
            "sortKey": SORT_KEY,
            "sortDirection": SORT_DIRECTION,
        }
        try:
            response = requests.get(
                endpoint, headers=headers, params=params, timeout=REQUEST_TIMEOUT_SECONDS
            )
            response.raise_for_status()
        except requests.RequestException as exc:
            print(f"[error] Request failed on page {page}: {exc}", file=sys.stderr)
            sys.exit(1)

        payload = response.json()
        records = payload.get("records", [])
        if not records:
            break

        all_records.extend(records)
        print(f"  Fetched page {page}: {len(records)} record(s)")
        page += 1

    return all_records


def extract_artist_and_title(record: dict) -> tuple[str, str]:
    """Pull artistName (nested under 'artist') and album title from a record."""
    artist = record.get("artist") or {}
    artist_name = artist.get("artistName", "Unknown Artist")
    title = record.get("title", "Unknown Title")
    return artist_name, title


def main() -> None:
    if not API_KEY:
        print(
            "LIDARR_API_KEY is not set. Add it to a local .env file "
            "(LIDARR_API_KEY=...) before running.",
            file=sys.stderr,
        )
        sys.exit(1)

    print(f"Querying {LIDARR_BASE_URL}/api/v1/wanted/missing ...")
    records = fetch_missing_records(LIDARR_BASE_URL, API_KEY)

    lines = []
    for record in records:
        artist_name, title = extract_artist_and_title(record)
        lines.append(f"{artist_name} - {title}")

    OUTPUT_FILE.write_text("\n".join(lines) + ("\n" if lines else ""), encoding="utf-8")

    print(f"Exported {len(lines)} missing album(s) to {OUTPUT_FILE}")


if __name__ == "__main__":
    main()
