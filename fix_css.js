const fs = require('fs');

function fix(file) {
    let css = fs.readFileSync(file, 'utf8');
    let noBlur = `
.box-inside, .box_headerbox {
    background-color: rgba(15, 23, 42, 0.85) !important;
    border: 1px solid rgba(255, 255, 255, 0.08) !important;
    border-radius: 14px !important;
    box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37) !important;
}
`;
    fs.writeFileSync(file, css + noBlur);
}

fix('ampache/public/themes/aether/default.css');
fix('ampache/public/themes/aether/dark.css');
