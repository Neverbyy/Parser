import { fileURLToPath, URL } from 'node:url'

import vue from '@vitejs/plugin-vue'
import { defineConfig } from 'vitest/config'

// Бэкенд слушает :8000, SPA живёт на :5173. Запросы к API и к Sanctum
// проксируются, поэтому браузер видит один origin: CORS не участвует,
// а сессионная кука ходит как обычная same-origin — это снимает
// самую частую проблему связки Sanctum + SPA, ошибку 419.
// В docker compose бэкенд доступен по имени сервиса (http://backend:8000),
// при локальном запуске — по IPv4-адресу. Именно IPv4, а не `localhost`:
// Node резолвит его сначала в ::1, и прокси упирается в ECONNREFUSED,
// если бэкенд слушает только IPv4.
const BACKEND_URL = process.env.BACKEND_URL ?? 'http://127.0.0.1:8000'

export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  server: {
    port: 5173,
    strictPort: true,
    proxy: {
      '/api': { target: BACKEND_URL, changeOrigin: true },
      '/sanctum': { target: BACKEND_URL, changeOrigin: true },
    },
  },
  test: {
    environment: 'jsdom',
    globals: true,
    include: ['src/**/*.spec.ts'],
    setupFiles: ['./src/test/setup.ts'],
  },
})
