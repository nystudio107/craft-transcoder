import {defineConfig} from 'vitepress'

export default defineConfig({
  title: 'Transcoder Plugin',
  description: 'Documentation for the Transcoder plugin',
  base: '/docs/transcoder/',
  lang: 'en-US',
  head: [
    ['meta', {content: 'https://github.com/nystudio107', property: 'og:see_also',}],
    ['meta', {content: 'https://twitter.com/nystudio107', property: 'og:see_also',}],
    ['meta', {content: 'https://youtube.com/nystudio107', property: 'og:see_also',}],
    ['meta', {content: 'https://www.facebook.com/newyorkstudio107', property: 'og:see_also',}],
  ],
  themeConfig: {
    socialLinks: [
      {icon: 'github', link: 'https://github.com/nystudio107'},
      {icon: 'twitter', link: 'https://twitter.com/nystudio107'},
    ],
    logo: '/img/plugin-logo.svg',
    editLink: {
      pattern: 'https://github.com/nystudio107/craft-transcoder/edit/develop/docs/docs/:path',
      text: 'Edit this page on GitHub'
    },
    algolia: {
      appId: 'VWUWF9S521',
      apiKey: 'db5c03f88e474cbf0356841089be7ffa',
      indexName: 'transcoder'
    },
    lastUpdatedText: 'Last Updated',
    sidebar: [
      {
        text: 'Topics',
        items: [
          {text: 'Transcoder Plugin', link: '/'},
          {text: 'Transcoder Overview', link: '/overview.html'},
          {text: 'Configuring Transcoder', link: '/configuring.html'},
          {text: 'Using Transcoder', link: '/using.html'},
        ],
      }
    ],
    nav: [
      {text: 'Home', link: 'https://nystudio107.com/plugins/transcoder'},
      {text: 'Store', link: 'https://plugins.craftcms.com/transcoder'},
      {text: 'Changelog', link: 'https://nystudio107.com/plugins/transcoder/changelog'},
      {text: 'Issues', link: 'https://github.com/nystudio107/craft-transcoder/issues'},
      {
        text: 'v4', items: [
          {text: 'v4', link: '/'},
          {text: 'v3', link: 'https://nystudio107.com/docs/transcoder/v3/'},
        ],
      },
    ],
  },
});
