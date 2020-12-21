// app.settings.js

// node modules
require('dotenv').config();

// settings
module.exports = {
    alias: {
    },
    copyright: '©2020 nystudio107.com',
    entry: {
        'transcoder': '../src/assetbundles/transcoder/src/js/Transcoder.js',
        'welcome': '../src/assetbundles/transcoder/src/js/Welcome.js',
    },
    extensions: ['.ts', '.js', '.vue', '.json'],
    name: 'transcoder',
    paths: {
        dist: '../../src/assetbundles/transcoder/dist/',
    },
    urls: {
        publicPath: () => process.env.PUBLIC_PATH || '',
    },
};
