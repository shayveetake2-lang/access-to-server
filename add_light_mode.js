const fs = require('fs');

const css = `
/* Light Mode Overrides */
body.light-mode, body.light-mode#main-page, body.light-mode #content {
    background: #f0f4f8 url('images/bg-light.jpg') no-repeat center center fixed !important;
    background-size: cover !important;
    color: #1e293b !important;
}

body.light-mode .box-inside, body.light-mode .box_headerbox {
    background-color: rgba(255, 255, 255, 0.85) !important;
    border: 1px solid rgba(0, 0, 0, 0.08) !important;
    color: #1e293b !important;
}

body.light-mode #sidebar, body.light-mode #header, body.light-mode #play_track, body.light-mode .jp-audio, body.light-mode #player {
    background-color: rgba(255, 255, 255, 0.75) !important;
    border: 1px solid rgba(0, 0, 0, 0.08) !important;
    color: #1e293b !important;
}

body.light-mode #sidebar ul li a {
    color: #334155 !important;
}

body.light-mode table.tabledata tbody td.grid_album,
body.light-mode table.tabledata tbody td.grid_artist,
body.light-mode .album-card, body.light-mode .artist-card {
    background-color: rgba(255, 255, 255, 0.6) !important;
    border: 1px solid rgba(0, 0, 0, 0.05) !important;
    color: #1e293b !important;
}

body.light-mode table.tabledata tbody td.grid_album:hover {
    background-color: rgba(255, 255, 255, 0.9) !important;
    border-color: rgba(168, 85, 247, 0.6) !important;
}

body.light-mode a, body.light-mode span, body.light-mode div {
    color: inherit;
}
`;

fs.appendFileSync('ampache/public/themes/aether/templates/default.css', css);
