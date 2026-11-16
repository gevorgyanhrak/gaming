/// <reference types="vitest/config" />
import { existsSync, readdirSync } from 'node:fs'
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

  // Each house slot (play/<id>/index.html, ADR 0024) is a page of its own next to the SPA, served at /play/<id>/.
  const root = fileURLToPath(new URL('.', import.meta.url))
  const input: Record<string, string> = { main: `${root}index.html` }
  for (const entry of readdirSync(new URL('./play', import.meta.url), { withFileTypes: true })) {
    if (
      entry.isDirectory() &&
      existsSync(new URL(`./play/${entry.name}/index.html`, import.meta.url))
    ) {
      input[`play/${entry.name}`] = `${root}play/${entry.name}/index.html`
    }
  }

  return {
    build: { rollupOptions: { input } },
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
