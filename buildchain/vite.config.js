import vue from '@vitejs/plugin-vue'
import path from 'path';
import ViteRestart from 'vite-plugin-restart';

// https://vitejs.dev/config/
export default ({ command }) => ({
  base: command === 'serve' ? '' : '/dist/',
  build: {
    brotliSize: false,
    emptyOutDir: true,
    manifest: true,
    outDir: '../src/assetbundles/transcoder/dist',
    rollupOptions: {
      input: {
        app: '/src/js/app.ts',
        welcome: '/src/js/welcome.ts',
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
