// module exports
module.exports = {
  mode: 'jit',
  purge: {
    content: [
      '../src/templates/**/*.{twig,html}',
      '../src/assetbundles/transcoder/src/vue/**/*.{vue,html}',
    ],
    layers: [
      'base',
      'components',
      'utilities',
    ],
    mode: 'layers',
    options: {
      whitelist: [
        '../src/assetbundles/transcoder/src/css/components/*.css',
      ],
    }
  },
  theme: {
  },
  corePlugins: {},
  plugins: [],
};
