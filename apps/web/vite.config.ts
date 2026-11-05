import { createReadStream, existsSync, statSync } from 'node:fs'
import path from 'node:path'
import tailwindcss from '@tailwindcss/vite'
import react from '@vitejs/plugin-react'
import { defineConfig, type Plugin } from 'vite'

const api = 'http://127.0.0.1:8000'
const publicStorage = path.resolve(import.meta.dirname, '../api/storage/app/public')

/**
 * Só em desenvolvimento: o servidor do PHP atende um pedido por vez, e um vídeo
 * travaria as capas. Em produção, as imagens públicas vêm da CDN.
 */
function publicStorageFiles(): Plugin {
  return {
    name: 'olavo-public-storage',
    configureServer(server) {
      server.middlewares.use('/storage', (request, response, next) => {
        const file = path.join(publicStorage, decodeURIComponent((request.url ?? '').split('?')[0]))

        if (!file.startsWith(publicStorage) || !existsSync(file) || !statSync(file).isFile()) return next()

        response.setHeader('Content-Type', 'image/webp')
        response.setHeader('Cache-Control', 'public, max-age=86400')
        createReadStream(file).pipe(response)
      })
    },
  }
}

export default defineConfig({
  plugins: [react(), tailwindcss(), publicStorageFiles()],
  // Analisa também as páginas sob demanda: sem isso, abrir uma delas reinicia o Vite no meio da navegação.
  optimizeDeps: { entries: ['index.html', 'src/**/*.tsx'] },
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
