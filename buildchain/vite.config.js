import vue from '@vitejs/plugin-vue'
import path from 'path';
import ViteRestart from 'vite-plugin-restart';

// https://vitejs.dev/config/
export default ({ command }) => ({
  base: command === 'serve' ? '' : '/dist/',
  build: {
    manifest: true,
    rollupOptions: {
      input: {
        app: '/src/js/app.ts',
        welcome: '/src/js/welcome.ts',
      },
      output: {
        sourcemap: true
      },
    }
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
