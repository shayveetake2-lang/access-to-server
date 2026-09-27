import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// https://vitejs.dev/config/
export default defineConfig({
  plugins: [react()],
  base: './',
  build: {
    target: ['es2018', 'safari13', 'chrome80'],
    // Explicit content-hashed filenames so every rebuild ships a fresh URL —
    // eliminates manual Cmd+Shift+R hard-refreshes on stale cached bundles.
    rollupOptions: {
      output: {
        entryFileNames: 'assets/[name].[hash].js',
        chunkFileNames: 'assets/[name].[hash].js',
        assetFileNames: 'assets/[name].[hash].[ext]',
      },
    },
  },
  server: {
    proxy: {
      '/ampache': {
        target: 'http://127.0.0.1:8888',
        changeOrigin: true,
      },
      '/modern-music-app/api_proxy.php': {
        target: 'http://127.0.0.1:8888',
        changeOrigin: true,
      },
      '/api': {
        target: 'http://127.0.0.1:8888',
        changeOrigin: true,
      }
    }
  }
})
