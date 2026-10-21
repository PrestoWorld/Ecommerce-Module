import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
  plugins: [react()],
  base: '/assets/modules/ecommerce/',
  build: {
    outDir: '../../public/assets/modules/ecommerce',
    emptyOutDir: true,
    manifest: true,
    sourcemap: true,
    lib: {
      entry: 'src/components/pages/index.ts',
      formats: ['es'],
      fileName: (format) => `ecommerce-screens.[hash].js`,
    },
    rollupOptions: {
      // Self-contained bundle: the host Dashboard does not expose React/antd
      // as window globals, so every dependency is bundled here.
      output: {},
    },
  },
  resolve: {
    conditions: ['development', 'browser'],
  },
});