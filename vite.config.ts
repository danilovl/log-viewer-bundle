import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import path from 'path';

export default defineConfig({
  plugins: [vue()],
  base: '/bundles/logviewer/build/',
  build: {
    outDir: path.resolve(__dirname, 'Resources/public/build'),
    emptyOutDir: true,
    manifest: true,
    rollupOptions: {
      input: path.resolve(__dirname, 'assets/main.ts'),
    },
  },
  resolve: {
    alias: {
      '@': path.resolve(__dirname, 'assets'),
    },
  },
});
