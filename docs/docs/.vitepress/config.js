module.exports = {
    title: 'Transcoder Plugin Documentation',
    description: 'Documentation for the Transcoder plugin',
    base: '/docs/transcoder/',
    lang: 'en-US',
    head: [
        ['meta', { content: 'https://github.com/nystudio107', property: 'og:see_also', }],
        ['meta', { content: 'https://www.youtube.com/channel/UCOZTZHQdC-unTERO7LRS6FA', property: 'og:see_also', }],
        ['meta', { content: 'https://www.facebook.com/newyorkstudio107', property: 'og:see_also', }],
    ],
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
