#!/usr/bin/env bash
# ===================================================================
# scripts/setup_python_deps.sh
# One-time setup script for Python 3 and library_agent.py dependencies
# on macOS High Sierra (10.13.6) x86_64
#
# Usage: bash scripts/setup_python_deps.sh
# ===================================================================
set -e

echo "=== Aether Library Agent — Python Dependency Setup ==="
echo "Target: macOS High Sierra x86_64"
echo ""

# Check if Python 3 is already installed
if command -v python3 >/dev/null 2>&1; then
    PYTHON3="$(which python3)"
    echo "[✔] Python 3 already installed at: $PYTHON3"
    python3 --version
else
    echo "[!] Python 3 not found."
    echo ""
    echo "Please install Python 3 manually by downloading the macOS installer:"
    echo "  https://www.python.org/ftp/python/3.11.9/python-3.11.9-macos11.pkg"
    echo ""
    echo "After installing, re-run this script."
    exit 1
fi

# Check pip3
if ! command -v pip3 >/dev/null 2>&1; then
    echo "[!] pip3 not found. Installing pip..."
    python3 -m ensurepip --upgrade
fi

echo ""
echo "=== Installing Python packages for library_agent.py ==="

python3 -m pip install --user --upgrade pip

# Install all required packages
PACKAGES=(
    "yt-dlp"
    "mutagen"
    "musicbrainzngs"
    "pyacoustid"
    "requests"
    "python-dotenv"
)

for pkg in "${PACKAGES[@]}"; do
    echo "[+] Installing $pkg..."
    python3 -m pip install --user "$pkg"
done

echo ""
echo "=== Verifying installations ==="
python3 -c "import yt_dlp; print('[✔] yt_dlp:', yt_dlp.version.__version__)"
python3 -c "import mutagen; print('[✔] mutagen:', mutagen.version_string)"
python3 -c "import musicbrainzngs; print('[✔] musicbrainzngs: OK')"
python3 -c "import acoustid; print('[✔] acoustid: OK')"
python3 -c "import requests; print('[✔] requests:', requests.__version__)"
python3 -c "import dotenv; print('[✔] python-dotenv: OK')"

echo ""
echo "=== Installing system tools ==="

# Check ffmpeg
if command -v ffmpeg >/dev/null 2>&1; then
    echo "[✔] ffmpeg already installed at: $(which ffmpeg)"
else
    echo "[!] ffmpeg not found."
    echo "    Please install ffmpeg from: https://evermeet.cx/ffmpeg/"
    echo "    Download the static binary, unzip, and move to /usr/local/bin/ffmpeg"
fi

# Check fpcalc (Chromaprint for AcoustID fingerprinting)
if command -v fpcalc >/dev/null 2>&1; then
    echo "[✔] fpcalc (Chromaprint) already installed at: $(which fpcalc)"
else
    echo "[!] fpcalc not found."
    echo "    Please install Chromaprint from: https://acoustid.org/chromaprint"
    echo "    Download the pre-compiled binary for macOS and move fpcalc to /usr/local/bin/fpcalc"
fi

echo ""
echo "=== Setup Complete! ==="
echo "Run the cron installer next:"
echo "  bash scripts/install_agent_cron.sh"
