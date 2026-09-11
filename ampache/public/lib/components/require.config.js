var components = {
    "packages": [
        {
            "name": "tag-it",
            "main": "tag-it-built.js"
        },
        {
            "name": "jquery-knob",
            "main": "jquery-knob-built.js"
        },
        {
            "name": "jquery-file-upload",
            "main": "jquery-file-upload-built.js"
        },
        {
            "name": "bootstrap",
            "main": "bootstrap-built.js"
        },
        {
            "name": "jquery",
            "main": "jquery-built.js"
        },
        {
            "name": "jquery-ui",
            "main": "jquery-ui-built.js"
        },
        {
            "name": "jquery-qrcode",
            "main": "jquery-qrcode-built.js"
        },
        {
            "name": "js-cookie",
            "main": "js-cookie-built.js"
        },
        {
            "name": "responsive-elements",
            "main": "responsive-elements-built.js"
        },
        {
            "name": "jscroll",
            "main": "jscroll-built.js"
        },
        {
            "name": "prettyphoto",
            "main": "prettyphoto-built.js"
        },
        {
            "name": "jquery-contextmenu",
            "main": "jquery-contextmenu-built.js"
        },
        {
            "name": "jstree",
            "main": "jstree-built.js"
        },
        {
            "name": "datetimepicker",
            "main": "datetimepicker-built.js"
        }
    ],
    "shim": {
        "bootstrap": {
            "deps": [
                "jquery"
            ]
        },
        "jquery-ui": {
            "deps": [
                "jquery"
            ],
            "exports": "jQuery"
        }
    },
    "baseUrl": "components"
};
if (typeof require !== "undefined" && require.config) {
    require.config(components);
} else {
    var require = components;
}
if (typeof exports !== "undefined" && typeof module !== "undefined") {
    module.exports = components;
}