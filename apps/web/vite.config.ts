import path from 'node:path'
import tailwindcss from '@tailwindcss/vite'
import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

const api = 'http://127.0.0.1:8000'

export default defineConfig({
  plugins: [react(), tailwindcss()],
  resolve: { alias: { '@': path.resolve(import.meta.dirname, 'src') } },
  server: {
    port: 5173,
    strictPort: true,
    // Mesma origem em desenvolvimento, como em produção: sem CORS e com cookies do Sanctum.
    proxy: {
      '/api': { target: api, changeOrigin: false },
      '/sanctum': { target: api, changeOrigin: false },
    },
  },
})
