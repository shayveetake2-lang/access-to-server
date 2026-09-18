#!/bin/bash
# scripts/run_sites_server.sh — Native Port 8880 Tenant Isolation Server for Deployed Sites
# Runs natively on macOS High Sierra (2011 MacBook Pro) without Docker overhead

BASE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SITES_DIR="${BASE_DIR}/sites"
PORT=8880
HOST="0.0.0.0"
PID_FILE="/tmp/serverflow_sites_8880.pid"

ACTION="${1:-start}"

case "$ACTION" in
    start)
        if [ -f "$PID_FILE" ]; then
            OLD_PID=$(cat "$PID_FILE" 2>/dev/null)
            if [ -n "$OLD_PID" ] && kill -0 "$OLD_PID" 2>/dev/null; then
                echo "Port $PORT isolation server is already running (PID: $OLD_PID)."
                exit 0
            fi
        fi

        echo "Starting isolated sites web server on ${HOST}:${PORT}..."
        echo "Root directory: ${SITES_DIR}"

        PHP_BIN=$(which php 2>/dev/null || echo "/usr/bin/php")
        if [ ! -x "$PHP_BIN" ]; then
            echo "Error: PHP binary not found."
            exit 1
        fi

        nohup "$PHP_BIN" -S "${HOST}:${PORT}" -t "${SITES_DIR}" > /tmp/serverflow_sites_8880.log 2>&1 &
        SERVER_PID=$!
        echo "$SERVER_PID" > "$PID_FILE"
        echo "Server started successfully on port ${PORT} with PID ${SERVER_PID}."
        ;;

    stop)
        if [ -f "$PID_FILE" ]; then
            OLD_PID=$(cat "$PID_FILE" 2>/dev/null)
            if [ -n "$OLD_PID" ]; then
                kill "$OLD_PID" 2>/dev/null
                rm -f "$PID_FILE"
                echo "Stopped server PID $OLD_PID."
            fi
        else
            echo "No PID file found at $PID_FILE."
        fi
        ;;

    status)
        if [ -f "$PID_FILE" ]; then
            OLD_PID=$(cat "$PID_FILE" 2>/dev/null)
            if [ -n "$OLD_PID" ] && kill -0 "$OLD_PID" 2>/dev/null; then
                echo "Port $PORT isolation server is running (PID: $OLD_PID)."
                exit 0
            fi
        fi
        echo "Port $PORT isolation server is not running."
        exit 1
        ;;

    restart)
        "$0" stop
        sleep 1
        "$0" start
        ;;

    *)
        echo "Usage: $0 {start|stop|restart|status}"
        exit 1
        ;;
esac
