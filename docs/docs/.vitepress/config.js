module.exports = {
    title: 'Transcoder Documentation',
    description: 'Documentation for the Transcoder plugin',
    base: '/docs/vite/',
    lang: 'en-US',
    themeConfig: {
        repo: 'nystudio107/craft-transcoder',
        docsDir: 'docs',
        algolia: {
            apiKey: '',
            indexName: 'craft-transcoder'
        },
        editLinks: true,
        editLinkText: 'Edit this page on GitHub',
        lastUpdated: 'Last Updated',
        sidebar: [
            { text: 'Transcoder Plugin', link: '/index' },
            { text: 'Transcoder Overview', link: '/overview' },
            { text: 'Configuring Transcoder', link: '/configuring' },
            { text: 'Using Transcoder', link: '/using' },
        ],
    },
};
