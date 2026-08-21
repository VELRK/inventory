import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig({
  base: '/plots/app/',
  plugins: [react()],
  server: {
    port: 5173,
    proxy: {
      '/plots/index.php/api': {
        target: 'http://localhost:8080',
        changeOrigin: true,
        rewrite: (path) => path.replace('/plots/index.php/api', '/inventory/index.php/api'),
      },
      '/plots/uploads': {
        target: 'http://localhost:8080',
        changeOrigin: true,
        rewrite: (path) => path.replace('/plots/uploads', '/inventory/uploads'),
      },
      '/api': {
        target: 'http://localhost:8080',
        changeOrigin: true,
        rewrite: (path) => '/inventory/index.php' + path,
      },
    },
  },
  test: {
    environment: 'node',
  },
})
