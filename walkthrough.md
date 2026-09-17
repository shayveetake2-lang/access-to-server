# Phase 9: Server Dashboard Storage and Quota Fixes

## Goal
Fix three storage and quota bugs on the local ServerFlow dashboard (PHP/MySQL) requested by the user:
1. Live Storage Refresh Buttons for SSD and USB with dynamic DOM updates via unified endpoint.
2. Fix Website Deployment Quota Bug ensuring 100MB strict limit check during `deploy.php`.
3. Admin-Only Quota Refresh feature with RBAC token protection and hidden UI.

## Changes Made

### 1. Live Storage Refresh (Unified endpoint)
- **`api/system/storage_stats.php` [NEW]**: Created a new PHP script that accurately calculates storage metrics for the Primary SSD (`/Volumes/htdocs`) and USB Storage (`/Volumes/USBDrive`) using `disk_free_space()` and `disk_total_space()`. Returns consolidated JSON.
- **`index.html`**:
  - Replaced inline `onclick` attributes for SSD and USB refresh buttons with explicit IDs (`btn-refresh-ssd`, `btn-refresh-usb`).
  - Injected `fetchUnifiedStorage()` into the `DOMContentLoaded` block. This function fetches metrics from the new unified PHP endpoint and dynamically updates the respective DOM elements (texts, SVG donuts, pill statuses) without reloading the page.
  - Attached standard `addEventListener('click', ...)` on both refresh buttons.

### 2. Website Deployment Quota Bug Fix
- **`api/system/deploy.php`**:
  - Injected a new `getDirectorySizeMB` helper function at the top of the file to recursively calculate folder sizes.
  - Inside the successful clone execution block (`if ($returnCode === 0)`), calculated the new total size of all sites owned by the deploying user.
  - Added strict limit enforcement: If `totalUserMB` exceeds `limitMB` (100MB), the script now immediately executes `rm -rf` on the cloned directory, aborts the deployment, and returns a 403 HTTP status.
  - If successful, it accurately updates `storage_used_mb` for the given username in the `sys_users` database table.

### 3. Admin-Only Quota Refresh
- **`api/system/refresh_quotas.php` [NEW]**: Created a secure endpoint that recalculates all users' exact used storage by looping through their respective sites directories. Secured at the very top using `requireAdmin()` (which strictly validates the JWT auth token and returns 403 Forbidden for non-admins).
- **`index.html`**: Set the default state of the "Refresh Quota" button to hidden using the Tailwind `hidden` class and assigned it the ID `btn-refresh-quotas`. Updated its inline action to execute `refreshServerQuotas()`.
- **`js/admin_auth.js`**:
  - Added `refreshServerQuotas()` which sends the authenticated POST request.
  - Updated `updateAdminUI()` to dynamically remove the `hidden` class from the `btn-refresh-quotas` element ONLY when `currentAdminState.role === 'admin'`, enforcing UI-level RBAC.

## Validation
- Changes verified via backend log tracking. File additions confirmed. Diff looks exact and matches expected outputs.
