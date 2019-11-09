// webpack.settings.js - webpack settings config

// node modules
require('dotenv').config();

// Webpack settings exports
// noinspection WebpackConfigHighlighting
module.exports = {
    name: "Transcoder",
    copyright: "nystudio107",
    paths: {
        src: {
            base: "./src/assetbundles/transcoder/src/",
            css: "./src/assetbundles/transcoder/src/css/",
            js: "./src/assetbundles/transcoder/src/js/"
        },
        dist: {
            base: "./src/assetbundles/transcoder/dist/",
            clean: [
                '**/*',
            ]
        },
        templates: "./src/templates/"
    },
    urls: {
        publicPath: () => process.env.PUBLIC_PATH || "",
    },
    vars: {
        cssName: "styles"
    },
    entries: {
        "transcoder": "Transcoder.js",
        "welcome": "Welcome.js"
    },
    babelLoaderConfig: {
        exclude: [
            /(node_modules|bower_components)/
        ],
    },
    copyWebpackConfig: [
    ],
    devServerConfig: {
        public: () => process.env.DEVSERVER_PUBLIC || "http://localhost:8080",
        host: () => process.env.DEVSERVER_HOST || "localhost",
        poll: () => process.env.DEVSERVER_POLL || false,
        port: () => process.env.DEVSERVER_PORT || 8080,
        https: () => process.env.DEVSERVER_HTTPS || false,
    },
    manifestConfig: {
        basePath: ""
    },
    purgeCssConfig: {
        paths: [
            "./src/templates/**/*.{twig,html}",
            "./node_modules/vuetable-2/src/components/**/*.{vue,html}",
            "./src/assetbundles/transcoder/src/vue/**/*.{vue,html}"
        ],
        whitelist: [
            "./src/assetbundles/transcoder/src/css/components/**/*.{css,pcss}"
        ],
        whitelistPatterns: [],
        extensions: [
            "html",
            "js",
            "twig",
            "vue"
        ]
    },
    saveRemoteFileConfig: [
    ],
    createSymlinkConfig: [
    ],
};
