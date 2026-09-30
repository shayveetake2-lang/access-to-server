#!/usr/bin/env bash
# ===================================================================
# scripts/check_agent_status.sh
# Color-coded DevOps telemetry monitor for library_agent.py
# Usage: bash scripts/check_agent_status.sh
# ===================================================================

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m'

echo -e "\n${BOLD}${CYAN}====================================================${NC}"
echo -e "${BOLD}${CYAN}       AETHER MUSIC AGENT — STATUS MONITOR           ${NC}"
echo -e "${BOLD}${CYAN}====================================================${NC}\n"

# 1. Cron Schedule Check
echo -e "${BOLD}[1] Cron Schedule Check:${NC}"
CRON_ENTRY=$(crontab -l 2>/dev/null | grep "[l]ibrary_agent.py" || true)
if [ -n "$CRON_ENTRY" ]; then
    echo -e "  Schedule: ${GREEN}LOCKED & SCHEDULED${NC}"
    echo -e "  Entry:    ${BLUE}$CRON_ENTRY${NC}"
else
    echo -e "  Schedule: ${RED}NOT SCHEDULED IN CRONTAB${NC}"
    echo -e "  Fix:      ${YELLOW}Run scripts/install_agent_cron.sh${NC}"
fi
echo ""

# 2. Active Process Check
echo -e "${BOLD}[2] Active Process Check:${NC}"
PROC_CHECK=$(ps aux | grep "[l]ibrary_agent.py" | grep -v grep || true)
if [ -n "$PROC_CHECK" ]; then
    echo -e "  Process:  ${GREEN}RUNNING IN MEMORY${NC}"
    echo -e "  Details:  ${PROC_CHECK}"
else
    echo -e "  Process:  ${YELLOW}IDLE (No active execution right now)${NC}"
fi
echo ""

# 3. Lockfile Status Check
echo -e "${BOLD}[3] Lockfile Status Check:${NC}"
LOCK_FILE="/tmp/library_agent.lock"
if [ -f "$LOCK_FILE" ]; then
    LOCK_PID=$(cat "$LOCK_FILE" 2>/dev/null || echo "Unknown")
    echo -e "  Status:   ${GREEN}${BOLD}ACTIVE (Currently processing a batch — PID: ${LOCK_PID})${NC}"
else
    echo -e "  Status:   ${BLUE}SLEEPING (Waiting for next cron window)${NC}"
fi
echo ""

# 4. Live Log Tail (Last 5 Lines)
echo -e "${BOLD}[4] Live Log Tail (/tmp/library_agent.log):${NC}"
LOG_FILE="/tmp/library_agent.log"
if [ -f "$LOG_FILE" ]; then
    echo -e "${CYAN}----------------------------------------------------${NC}"
    tail -n 5 "$LOG_FILE"
    echo -e "${CYAN}----------------------------------------------------${NC}"
else
    echo -e "  ${YELLOW}Log file not found yet at ${LOG_FILE}${NC}"
    echo -e "  ${YELLOW}It will appear after the first cron execution.${NC}"
fi
echo ""
