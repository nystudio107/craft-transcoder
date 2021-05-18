module.exports = {
    title: 'Transcoder Documentation',
    description: 'Documentation for the Transcoder plugin',
    base: '/docs/transcoder/',
    lang: 'en-US',
    themeConfig: {
        repo: 'nystudio107/craft-transcoder',
        docsDir: 'docs/docs',
        docsBranch: 'v1',
        algolia: {
            apiKey: '14f28d1e87ded365acc21d0e921732e6',
            indexName: 'transcoder'
        },
        editLinks: true,
        editLinkText: 'Edit this page on GitHub',
        lastUpdated: 'Last Updated',
        sidebar: [
            { text: 'Transcoder Plugin', link: '/' },
            { text: 'Transcoder Overview', link: '/overview' },
            { text: 'Configuring Transcoder', link: '/configuring' },
            { text: 'Using Transcoder', link: '/using' },
        ],
    },
};
