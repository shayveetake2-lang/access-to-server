# Phase 10: Media Request System

## Goal
Implement a media request system for users to request Movies, TV Shows, and Music, and for admins to view and manage these requests within the ServerFlow dashboard.

## Changes Made

### 1. Database Setup
- Created the `media_requests` table in the database with columns for `id`, `user_id`, `media_title`, `media_type`, `status`, and `request_date`. Ensure fallback compatibility across MySQL and SQLite.

### 2. Standard User View
- **Frontend (`index.html`)**: Injected the Tailwind CSS HTML for the "Request Media" form (containing Title input, Type dropdown, and Submit button) into the Overview tab for standard users. Included the JavaScript handler `submitMediaRequest()` to process submissions.
- **Backend (`api/media/submit_request.php`)**: Created the endpoint to parse JSON form submissions, verify the user's JWT token, and securely insert the request into the `media_requests` table using PDO prepared statements.

### 3. Admin Dashboard View
- **Frontend (`index.html` & `js/admin_auth.js`)**: Injected the Tailwind CSS HTML for the "Media Requests Tracker" table inside the protected Admin Portal (Settings tab). Added the JavaScript function `fetchMediaRequests()` which automatically populates the table when an admin logs in.
- **Backend (`api/media/get_requests.php`)**: Created the endpoint to fetch all requests across all users, sorted by date. Strictly protected this endpoint using `requireAdmin()` to ensure only authenticated admins can view the requests.

## Validation
- Form HTML successfully injected into the correct protected/unprotected DOM zones.
- Endpoints created and wired together with fetch calls.
