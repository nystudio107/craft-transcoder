module.exports = {
  title: 'Transcoder Plugin Documentation',
  description: 'Documentation for the Transcoder plugin',
  base: '/docs/transcoder/',
  lang: 'en-US',
  head: [
    ['meta', {content: 'https://github.com/nystudio107', property: 'og:see_also',}],
    ['meta', {content: 'https://www.youtube.com/channel/UCOZTZHQdC-unTERO7LRS6FA', property: 'og:see_also',}],
    ['meta', {content: 'https://www.facebook.com/newyorkstudio107', property: 'og:see_also',}],
  ],
  themeConfig: {
    repo: 'nystudio107/craft-transcoder',
    docsDir: 'docs/docs',
    docsBranch: 'develop',
    algolia: {
      appId: 'VWUWF9S521',
      apiKey: 'db5c03f88e474cbf0356841089be7ffa',
      indexName: 'transcoder'
    },
    editLinks: true,
    editLinkText: 'Edit this page on GitHub',
    lastUpdated: 'Last Updated',
    sidebar: [
      {text: 'Transcoder Plugin', link: '/'},
      {text: 'Transcoder Overview', link: '/overview.html'},
      {text: 'Configuring Transcoder', link: '/configuring.html'},
      {text: 'Using Transcoder', link: '/using.html'},
    ],
  },
};
