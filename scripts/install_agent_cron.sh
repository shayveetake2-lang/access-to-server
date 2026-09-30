#!/usr/bin/env bash
# ===================================================================
# scripts/install_agent_cron.sh
# Injects library_agent.py into crontab to run every 4 hours (0 */4 * * *)
# Explicitly uses /opt/homebrew/bin/python3 for M1 Apple Silicon
# Output is routed to /tmp/library_agent.log
# ===================================================================
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
AGENT_SCRIPT="$SCRIPT_DIR/library_agent.py"
LOG_FILE="/tmp/library_agent.log"

# Explicitly use Homebrew Python 3 on M1 Apple Silicon
if [ -x "/opt/homebrew/bin/python3" ]; then
    PYTHON_BIN="/opt/homebrew/bin/python3"
elif [ -x "/usr/local/bin/python3" ]; then
    PYTHON_BIN="/usr/local/bin/python3"
else
    PYTHON_BIN="$(which python3)"
fi

echo "[INFO] Using Python binary: $PYTHON_BIN"
CRON_CMD="0 */4 * * * $PYTHON_BIN $AGENT_SCRIPT >> $LOG_FILE 2>&1"

# Read existing crontab (gracefully handles empty crontab)
CURRENT_CRON="$(crontab -l 2>/dev/null || true)"

if echo "$CURRENT_CRON" | grep -Fq "library_agent.py"; then
    echo "[*] Existing library_agent cron entry found. Updating schedule..."
    NEW_CRON="$(echo "$CURRENT_CRON" | grep -Fv "library_agent.py")"
    (echo "$NEW_CRON"; echo "$CRON_CMD") | sed '/^$/d' | crontab -
else
    echo "[+] Injecting 4-hour cron schedule..."
    (echo "$CURRENT_CRON"; echo "# Aether Autonomous Music Agent Daemon"; echo "$CRON_CMD") | sed '/^$/d' | crontab -
fi

echo "[✔] Crontab successfully installed!"
echo "--------------------------------------------------------"
crontab -l | grep "library_agent.py"
echo "--------------------------------------------------------"
