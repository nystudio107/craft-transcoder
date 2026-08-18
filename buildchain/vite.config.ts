import {defineConfig} from 'vite';
import createVuePlugin from '@vitejs/plugin-vue';
import viteCompressionPlugin from 'vite-plugin-compression';
import checker from 'vite-plugin-checker';
import tailwindcss from "@tailwindcss/vite";
import {visualizer} from 'rollup-plugin-visualizer';
import * as path from 'path';

// https://vitejs.dev/config/
export default defineConfig(({command}) => ({
  base: command === 'serve' ? '' : '/dist/',
  build: {
    emptyOutDir: true,
    manifest: 'manifest.json',
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
    createVuePlugin(),
    viteCompressionPlugin({
      filter: /\.(js|mjs|json|css|map)$/i
    }),
    visualizer({
      filename: '../src/web/assets/dist/stats.html',
      template: 'treemap',
      sourcemap: true,
    }),
    tailwindcss(),
    checker({
      eslint: {
        lintCommand: 'eslint "./src/**/*.{js,ts}"',
        useFlatConfig: true,
        dev: {
          overrideConfig: {
            cache: true,
          }
        }
      },
      stylelint: {
        lintCommand: 'stylelint ./src/**/*.{css,scss,sass,pcss} --fix',
        dev: {
          overrideConfig: {
            cache: true,
          }
        }
      },
      typescript: true,
      vueTsc: true,
    }),
  ],
  resolve: {
    alias: [
      {find: '@', replacement: path.resolve(__dirname, './src')},
    ],
    preserveSymlinks: true,
  },
  server: {
    fs: {
      strict: false
    },
    host: '0.0.0.0',
    origin: 'http://localhost:' + process.env.DEV_PORT,
    port: parseInt(process.env.DEV_PORT),
    strictPort: true,
  }
}));
