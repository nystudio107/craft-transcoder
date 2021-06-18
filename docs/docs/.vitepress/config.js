module.exports = {
    title: 'Transcoder Documentation',
    description: 'Documentation for the Transcoder plugin',
    base: '/docs/transcoder/',
    lang: 'en-US',
    head: [
        [
            'script',
            {},
            "(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)})(window,document,'script','https://www.google-analytics.com/analytics.js','ga');ga('create', 'UA-69117511-1', 'auto');ga('require', 'displayfeatures');ga('send', 'pageview');"
        ],
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
