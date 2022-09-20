import {defineConfig} from 'vite';
import vue from '@vitejs/plugin-vue'
import ViteRestart from 'vite-plugin-restart';
import viteCompression from 'vite-plugin-compression';
import {visualizer} from 'rollup-plugin-visualizer';
import {nodeResolve} from '@rollup/plugin-node-resolve';
import * as path from 'path';

// https://vitejs.dev/config/
export default defineConfig(({command}) => ({
  base: command === 'serve' ? '' : '/dist/',
  build: {
    emptyOutDir: true,
    manifest: true,
    outDir: '../src/web/assets/dist',
    rollupOptions: {
      input: {
        app: 'src/js/app.ts',
        welcome: 'src/js/welcome.ts',
      },
      output: {
        sourcemap: true
      },
    }
  },
  plugins: [
    nodeResolve({
      moduleDirectories: [
        path.resolve('./node_modules'),
      ],
    }),
    ViteRestart({
      reload: [
        './src/templates/**/*',
      ],
    }),
    vue(),
    viteCompression({
      filter: /\.(js|mjs|json|css|map)$/i
    }),
    visualizer({
      filename: '../src/web/assets/dist/stats.html',
      template: 'treemap',
      sourcemap: true,
    }),
  ],
  publicDir: '../src/web/assets/public',
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src')
    },
    preserveSymlinks: true,
  },
  server: {
    fs: {
      strict: false
    },
    host: '0.0.0.0',
    origin: 'http://localhost:3001',
    port: 3001,
    strictPort: true,
  }
}));
