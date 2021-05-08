import vue from '@vitejs/plugin-vue'
import path from 'path';
import ViteRestart from 'vite-plugin-restart';

// https://vitejs.dev/config/
export default ({ command }) => ({
  base: command === 'serve' ? '' : '/dist/',
  build: {
    emptyOutDir: true,
    manifest: true,
    outDir: '../src/assetbundles/transcoder/dist',
    rollupOptions: {
      input: {
        'transcoder': '/src/js/Transcoder.js',
        'welcome': '/src/js/Welcome.js',
      }
    },
  },
  plugins: [
    ViteRestart({
      reload: [
          '../src/templates/**/*',
      ],
    }),
    vue(),
  ],
  resolve: {
    alias: {
      '@': path.resolve('/src/'),
    },
  },
});
