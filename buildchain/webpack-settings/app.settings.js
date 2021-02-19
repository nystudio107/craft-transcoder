// app.settings.js

// node modules
require('dotenv').config();
const path = require('path');

// settings
module.exports = {
    alias: {
        '@css': path.resolve('../src/assetbundles/transcoder/src/css'),
        '@img': path.resolve('../src/assetbundles/transcoder/src/img'),
        '@js': path.resolve('../src/assetbundles/transcoder/src/js'),
        '@vue': path.resolve('../src/assetbundles/transcoder/src/vue'),
    },
    copyright: '©2020 nystudio107.com',
    entry: {
        'transcoder': '@js/Transcoder.js',
        'welcome': '@js/Welcome.js',
    },
    extensions: ['.ts', '.js', '.vue', '.json'],
    name: 'transcoder',
    paths: {
        dist: path.resolve('../src/assetbundles/transcoder/dist/'),
    },
    urls: {
        publicPath: () => process.env.PUBLIC_PATH || '',
    },
};
