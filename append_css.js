const fs = require('fs');

const cssOverride = `
/* =========================================================
   AETHER GLASS - HIGH SIERRA COMPATIBLE OVERRIDES
   ========================================================= */

/* 1. Canvas Background */
body, body#main-page, #content {
    background: #090d16 url('images/bg-cosmic.jpg') no-repeat center center fixed !important;
    background-size: cover !important;
    color: #e2e8f0;
}

/* 2. Glassmorphism Panels */
#sidebar, #header, #play_track, .jp-audio, .box-inside, .box_headerbox, #player {
    background-color: rgba(15, 23, 42, 0.85) !important;
    -webkit-backdrop-filter: blur(16px) !important;
    backdrop-filter: blur(16px) !important;
    border: 1px solid rgba(255, 255, 255, 0.08) !important;
    border-radius: 14px !important;
    box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37) !important;
}

/* Give sidebar and header some breathing room for floating effect */
#sidebar {
    margin: 12px !important;
    height: calc(100vh - 120px) !important;
    overflow-y: auto !important;
}

#header {
    margin: 12px 12px 12px 0 !important;
    border-radius: 14px !important;
}

/* Make content background transparent so cosmic bg shows through */
#content {
    background: transparent !important;
    border: none !important;
    box-shadow: none !important;
}

/* 3. Sticky Bottom Player */
#play_track, .jp-audio, #player {
    position: fixed !important;
    bottom: 12px !important;
    left: 20px !important;
    right: 20px !important;
    z-index: 9999 !important;
    width: auto !important;
    display: flex !important;
    align-items: center;
    padding: 10px 20px !important;
}

/* 4. Accents & Gradients */
/* Active Sidebar Tab */
#sidebar ul li.active a,
#sidebar ul li a:hover {
    background: linear-gradient(135deg, #a855f7, #6366f1) !important;
    color: #ffffff !important;
    border-radius: 8px !important;
    box-shadow: 0 0 12px rgba(168, 85, 247, 0.4) !important;
}

/* Play Buttons / Action Buttons */
.button, .btn, input[type="submit"], input[type="button"], button,
.jp-play, .play-button {
    background: linear-gradient(135deg, #a855f7, #6366f1) !important;
    color: #ffffff !important;
    border: none !important;
    border-radius: 20px !important;
    box-shadow: 0 0 12px rgba(168, 85, 247, 0.5) !important;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.button:hover, .btn:hover, input[type="submit"]:hover, button:hover {
    transform: translateY(-2px);
    box-shadow: 0 0 16px rgba(168, 85, 247, 0.7) !important;
}

/* Volume Bar & Progress Bar */
.jp-play-bar, .jp-volume-bar-value, .progress-bar {
    background: linear-gradient(135deg, #a855f7, #6366f1) !important;
    box-shadow: 0 0 8px rgba(168, 85, 247, 0.5) !important;
    border-radius: 4px !important;
}

/* Cards & Grid Items */
table.tabledata tbody td.grid_album,
table.tabledata tbody td.grid_artist,
.album-card, .artist-card {
    background-color: rgba(15, 23, 42, 0.6) !important;
    -webkit-backdrop-filter: blur(14px) !important;
    backdrop-filter: blur(14px) !important;
    border: 1px solid rgba(255, 255, 255, 0.05) !important;
    border-radius: 12px !important;
    padding: 10px !important;
    margin: 5px !important;
    transition: transform 0.2s ease;
}

table.tabledata tbody td.grid_album:hover {
    transform: translateY(-4px);
    background-color: rgba(15, 23, 42, 0.8) !important;
    border-color: rgba(168, 85, 247, 0.4) !important;
}

/* Scrollbar restyling for glass */
::-webkit-scrollbar-thumb {
    background: rgba(168, 85, 247, 0.4) !important;
    border-radius: 10px !important;
}
::-webkit-scrollbar-thumb:hover {
    background: rgba(168, 85, 247, 0.7) !important;
}
`;

fs.appendFileSync('ampache/public/themes/aether/dark.css', cssOverride);
