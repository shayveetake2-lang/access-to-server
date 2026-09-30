#!/usr/bin/env bash
# ===================================================================
# scripts/bootstrap_python3.sh
# Downloads and installs Python 3.11 for macOS High Sierra (x86_64)
# then installs all library_agent.py pip dependencies.
# Run via: bash scripts/bootstrap_python3.sh
# ===================================================================
set -e

PYTHON_PKG_URL="https://www.python.org/ftp/python/3.11.9/python-3.11.9-macos11.pkg"
PYTHON_PKG="/tmp/python311.pkg"

echo "==========================================================================================="
echo "  Aether Music Agent — Python 3 Bootstrap (macOS High Sierra / Intel Mac)"
echo "==========================================================================================="
echo ""

# --- Step 1: Check if Python 3 is already available ---
if command -v python3 >/dev/null 2>&1 && python3 -c "import sys; assert sys.version_info >= (3,9)" 2>/dev/null; then
    PYTHON3_BIN="$(command -v python3)"
    echo "[✔] Python 3 already installed: $($PYTHON3_BIN --version)"
else
    echo "[1/4] Downloading Python 3.11.9 installer (~45MB)..."
    curl -L "$PYTHON_PKG_URL" -o "$PYTHON_PKG"

    echo "[2/4] Installing Python 3.11.9 (requires sudo)..."
    sudo installer -pkg "$PYTHON_PKG" -target /
    echo "[✔] Python 3.11.9 installed."
    PYTHON3_BIN="/usr/local/bin/python3"
fi

# --- Step 2: Ensure pip is available ---
echo ""
echo "[3/4] Upgrading pip..."
$PYTHON3_BIN -m ensurepip --upgrade 2>/dev/null || true
$PYTHON3_BIN -m pip install --user --upgrade pip --quiet

# --- Step 3: Install all library_agent.py dependencies ---
echo ""
echo "[4/4] Installing Python packages..."
$PYTHON3_BIN -m pip install --user --upgrade \
    yt-dlp \
    mutagen \
    musicbrainzngs \
    pyacoustid \
    requests \
    python-dotenv

echo ""
echo "--- Verification ---"
$PYTHON3_BIN -c "import yt_dlp; print('[✔] yt-dlp:', yt_dlp.version.__version__)"
$PYTHON3_BIN -c "import mutagen; print('[✔] mutagen:', mutagen.version_string)"
$PYTHON3_BIN -c "import musicbrainzngs; print('[✔] musicbrainzngs: OK')"
$PYTHON3_BIN -c "import acoustid; print('[✔] acoustid: OK')"
$PYTHON3_BIN -c "import requests; print('[✔] requests:', requests.__version__)"
$PYTHON3_BIN -c "import dotenv; print('[✔] python-dotenv: OK')"

echo ""
echo "--- Installing system tools ---"

# ffmpeg static binary (Intel macOS)
if ! command -v ffmpeg >/dev/null 2>&1; then
    echo "[!] Installing ffmpeg (static binary)..."
    FFMPEG_URL="https://evermeet.cx/ffmpeg/getrelease/ffmpeg/zip"
    mkdir -p /tmp/ffmpeg_install
    if curl -sL "$FFMPEG_URL" -o /tmp/ffmpeg_install/ffmpeg.zip 2>/dev/null; then
        cd /tmp/ffmpeg_install && unzip -o ffmpeg.zip
        sudo mv ffmpeg /usr/local/bin/ffmpeg && sudo chmod +x /usr/local/bin/ffmpeg
        echo "[✔] ffmpeg installed at /usr/local/bin/ffmpeg"
    else
        echo "[!] Could not auto-download ffmpeg. Please manually download from https://evermeet.cx/ffmpeg/"
        echo "    Unzip and move the binary: sudo mv ffmpeg /usr/local/bin/ && sudo chmod +x /usr/local/bin/ffmpeg"
    fi
else
    echo "[✔] ffmpeg already installed: $(which ffmpeg)"
fi

# fpcalc (Chromaprint - needed for AcoustID fingerprinting)
if ! command -v fpcalc >/dev/null 2>&1; then
    echo "[!] fpcalc not found. Installing Chromaprint..."
    CHROMA_URL="https://github.com/acoustid/chromaprint/releases/download/v1.5.1/chromaprint-fpcalc-1.5.1-macos-x86_64.tar.gz"
    mkdir -p /tmp/chromaprint_install
    if curl -sL "$CHROMA_URL" -o /tmp/chromaprint_install/chroma.tar.gz 2>/dev/null; then
        cd /tmp/chromaprint_install && tar -xzf chroma.tar.gz
        sudo mv chromaprint-fpcalc-*/fpcalc /usr/local/bin/fpcalc && sudo chmod +x /usr/local/bin/fpcalc
        echo "[✔] fpcalc installed at /usr/local/bin/fpcalc"
    else
        echo "[!] Could not auto-download fpcalc. Download from: https://acoustid.org/chromaprint"
    fi
else
    echo "[✔] fpcalc already installed: $(which fpcalc)"
fi

# --- Step 4: Update crontab with correct python3 binary path ---
echo ""
echo "--- Updating crontab with correct Python path ---"
ACTUAL_PY3="$($PYTHON3_BIN -c 'import sys; print(sys.executable)')"
AGENT_SCRIPT="$(dirname "$0")/library_agent.py"
AGENT_SCRIPT="$(cd "$(dirname "$AGENT_SCRIPT")" && pwd)/library_agent.py"
LOG_FILE="/tmp/library_agent.log"
CRON_CMD="0 */4 * * * $ACTUAL_PY3 $AGENT_SCRIPT >> $LOG_FILE 2>&1"

CURRENT_CRON="$(crontab -l 2>/dev/null | grep -Fv 'library_agent.py' || true)"
(printf '%s\n' "$CURRENT_CRON"; printf '%s\n' "# Aether Autonomous Music Agent Daemon"; printf '%s\n' "$CRON_CMD") | grep -v '^$' | crontab -

echo "[✔] Crontab updated with Python at: $ACTUAL_PY3"
echo "    Schedule: 0 */4 * * * (every 4 hours)"
echo ""
echo "==========================================================================================="
echo "  Bootstrap COMPLETE! The music agent will run automatically every 4 hours."
echo "  Logs: tail -f /tmp/library_agent.log"
echo "==========================================================================================="
