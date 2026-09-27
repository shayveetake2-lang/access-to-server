#!/bin/bash
# scripts/deploy_usb_security.sh - Ensures /Volumes/USBDrive/.htaccess is locked down

USB_PATHS=("/Volumes/USBDrive" "/Volumes/USBDrive 1" "/Volumes/USBDrive2")
HTACCESS_SRC="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/usb_drive.htaccess"

for usb in "${USB_PATHS[@]}"; do
    if [ -d "$usb" ] && [ -w "$usb" ]; then
        cp -f "$HTACCESS_SRC" "$usb/.htaccess"
        chmod 644 "$usb/.htaccess"
        echo "[SECURITY] Applied php_flag engine off .htaccess to $usb"
    fi
done
