import os
import subprocess
import urllib.request

REPORT_FILE = "missing_albums_report.txt"
APPROVED_FILE = "approved_downloads.txt"
BATCH_SIZE = 100
SYNC_URL = "http://10.247.192.231:8888/auto_organizer.php"

def process_batch():
    if not os.path.exists(REPORT_FILE):
        print(f"Error: {REPORT_FILE} not found.")
        return

    with open(REPORT_FILE, "r", encoding="utf-8") as f:
        lines = [line.strip() for line in f if line.strip()]

    if not lines:
        print("No albums left to process in the report file!")
        return

    # Take the top batch
    current_batch = lines[:BATCH_SIZE]
    remaining = lines[BATCH_SIZE:]

    # Write formatted queries to approved_downloads.txt
    with open(APPROVED_FILE, "w", encoding="utf-8") as f:
        for entry in current_batch:
            if not entry.startswith("ytsearch1:"):
                f.write(f'ytsearch1:"{entry} full album audio"\n')
            else:
                f.write(f"{entry}\n")

    # Update the report file to remove processed albums
    with open(REPORT_FILE, "w", encoding="utf-8") as f:
        for entry in remaining:
            f.write(f"{entry}\n")

    print(f"[*] Queued {len(current_batch)} albums into {APPROVED_FILE}.")
    print(f"[*] {len(remaining)} albums remaining in {REPORT_FILE}.")

    # Run the archiver
    print("[*] Launching safe_audio_archiver.py...")
    result = subprocess.run(["python3", "safe_audio_archiver.py"])

    # Trigger server sync if downloads succeeded
    if result.returncode == 0:
        print("[*] Downloads finished. Triggering Ampache Auto-Organizer...")
        try:
            req = urllib.request.Request(SYNC_URL, headers={"User-Agent": "BatchDownloader/1.0"})
            with urllib.request.urlopen(req, timeout=30) as resp:
                print(f"[+] Server sync triggered successfully (HTTP {resp.status})")
        except Exception as e:
            print(f"[-] Could not reach sync endpoint: {e}")
    else:
        print("[-] safe_audio_archiver.py exited with errors.")

if __name__ == "__main__":
    process_batch()
