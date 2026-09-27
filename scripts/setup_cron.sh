#!/usr/bin/env bash
# scripts/setup_cron.sh
# Automates the setup of the background storage telemetry cron job on macOS High Sierra / MAMP

set -e

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CRON_SCRIPT="$DIR/api/cron_storage.php"

# Detect best available PHP binary
PHP_BIN=""
if [ -x "/Applications/MAMP/bin/php/php7.4.33/bin/php" ]; then
    PHP_BIN="/Applications/MAMP/bin/php/php7.4.33/bin/php"
elif [ -x "/Applications/MAMP/bin/php/php7.4.21/bin/php" ]; then
    PHP_BIN="/Applications/MAMP/bin/php/php7.4.21/bin/php"
elif [ -x "/usr/bin/php" ]; then
    PHP_BIN="/usr/bin/php"
elif command -v php >/dev/null 2>&1; then
    PHP_BIN="$(command -v php)"
fi

if [ -z "$PHP_BIN" ]; then
    echo "[ERROR] No PHP binary could be found on this system." >&2
    exit 1
fi

echo "[INFO] Using PHP Binary: $PHP_BIN"
echo "[INFO] Target Cron Script: $CRON_SCRIPT"

# Test executing the script once to confirm it succeeds without errors
echo "[INFO] Running initial storage telemetry check..."
"$PHP_BIN" "$CRON_SCRIPT"

# Define the cron line
CRON_LINE="*/2 * * * * $PHP_BIN $CRON_SCRIPT >/dev/null 2>&1"

# Check if entry already exists in current crontab
CURRENT_CRON="$(crontab -l 2>/dev/null || true)"

if echo "$CURRENT_CRON" | grep -Fq "$CRON_SCRIPT"; then
    echo "[OK] Cron job for cron_storage.php is already installed:"
    echo "$CURRENT_CRON" | grep -F "$CRON_SCRIPT"
else
    echo "[INFO] Installing 2-minute recurring cron job..."
    NEW_CRON="$(echo -e "${CURRENT_CRON}\n# ServerFlow Storage Telemetry Daemon\n${CRON_LINE}")"
    echo "$NEW_CRON" | sed '/^$/d' | crontab -
    echo "[SUCCESS] Crontab successfully installed!"
fi

echo "[INFO] Current Crontab entries:"
crontab -l
