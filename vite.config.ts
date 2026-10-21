import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
  plugins: [react()],
  // Library builds don't replace process.env.* — bundled deps (React/antd)
  // still reference process.env.NODE_ENV at runtime in the browser.
  define: {
    'process.env.NODE_ENV': JSON.stringify('production'),
  },
  base: '/assets/modules/ecommerce/',
  build: {
    outDir: '../../public/assets/modules/ecommerce',
    emptyOutDir: true,
    manifest: true,
    sourcemap: true,
    lib: {
      entry: 'src/components/pages/index.ts',
      formats: ['iife'],
      name: 'EcommerceScreens',
      fileName: () => `ecommerce-screens.[hash].js`,
    },
    rollupOptions: {
      // React must come from the host (single instance): the Dashboard exposes
      // window.React / ReactDOM / ReactDOMClient / ReactJSXRuntime globals.
      // Everything else (antd, react-query, ...) is bundled here.
      external: [
        'react',
        'react-dom',
        'react-dom/client',
        'react/jsx-runtime',
        'react/jsx-dev-runtime',
      ],
      output: {
        globals: {
          react: 'React',
          'react-dom': 'ReactDOM',
          'react-dom/client': 'ReactDOMClient',
          'react/jsx-runtime': 'ReactJSXRuntime',
          'react/jsx-dev-runtime': 'ReactJSXRuntime',
        },
      },
    },
  },
  resolve: {
    conditions: ['development', 'browser'],
  },
});