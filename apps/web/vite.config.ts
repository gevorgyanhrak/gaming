/// <reference types="vitest/config" />
import { fileURLToPath, URL } from 'node:url'
import tailwindcss from '@tailwindcss/vite'
import react from '@vitejs/plugin-react'
import { defineConfig, loadEnv } from 'vite'

// https://vite.dev/config/
export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '')
  const apiTarget = env.API_PROXY_TARGET || 'http://localhost:8000'

  // In development the SPA and API share one origin through this proxy, so session
  // cookies and CSRF work without cross-origin setup. Production uses VITE_API_BASE_URL.
  const proxy = Object.fromEntries(
    ['/api', '/sanctum', '/broadcasting'].map((path) => [path, { target: apiTarget }]),
  )

  return {
    plugins: [react(), tailwindcss()],
    resolve: {
      alias: { '@': fileURLToPath(new URL('./src', import.meta.url)) },
    },
    server: { port: 5175, strictPort: true, proxy },
    preview: { port: 5175, strictPort: true, proxy },
    test: {
      environment: 'jsdom',
      setupFiles: ['./src/test/setup.ts'],
      css: false,
      restoreMocks: true,
    },
  }
})
