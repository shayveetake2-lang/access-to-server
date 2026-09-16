#!/bin/bash
PID=$(cat /Applications/MAMP/Library/logs/httpd.pid 2>/dev/null)
if [ -z "$PID" ]; then
    PID=$(cat /Applications/MAMP/logs/httpd.pid 2>/dev/null)
fi

if [ -n "$PID" ]; then
    kill -USR1 "$PID" 2>/dev/null && echo "Apache graceful reload signal sent to PID $PID." && exit 0
fi

/Applications/MAMP/Library/bin/apachectl graceful 2>/dev/null && echo "Apache graceful reload executed via apachectl." && exit 0

echo "Fallback: Invoking restart script"
/Applications/MAMP/bin/stopApache.sh 2>/dev/null
sleep 1
/Applications/MAMP/bin/startApache.sh 2>/dev/null
echo "Apache restart completed."
