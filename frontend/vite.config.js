import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { VitePWA } from 'vite-plugin-pwa'

export default defineConfig({
  plugins: [
    vue(),
    VitePWA({
      registerType: 'autoUpdate',
      includeAssets: ['favicon.svg'],
      manifest: {
        name: 'SaunaSpeak - Learn Finnish',
        short_name: 'SaunaSpeak',
        description: 'Learn Finnish through short daily Sauna Sessions',
        theme_color: '#12141a',
        background_color: '#12141a',
        display: 'standalone',
        start_url: '/',
        icons: [
          { src: 'pwa-192x192.png', sizes: '192x192', type: 'image/png' },
          { src: 'pwa-512x512.png', sizes: '512x512', type: 'image/png' },
          { src: 'pwa-512x512.png', sizes: '512x512', type: 'image/png', purpose: 'maskable' }
        ]
      },
      workbox: {
        // Cache the UI shell (JS/CSS/HTML/icons); never cache API calls.
        // Scenario art (scenes/) stays out of the precache - ~2MB that only
        // Löyly+ users in a scenario need; it's runtime-cached below instead.
        globPatterns: ['**/*.{js,css,html,svg,png,woff2}'],
        globIgnores: ['scenes/**', 'sw-kill.js'],
        navigateFallback: '/index.html',
        navigateFallbackDenylist: [/^\/api/, /^\/audio/],
        // Sentence + word MP3s are immutable once generated - cache-first keeps them offline.
        runtimeCaching: [
          {
            urlPattern: /\/audio\/.*\.mp3$/,
            handler: 'CacheFirst',
            options: {
              cacheName: 'finnish-audio',
              expiration: { maxEntries: 600, maxAgeSeconds: 60 * 60 * 24 * 90 }
            }
          },
          {
            urlPattern: /\/scenes\/.*\.(png|jpg)$/,
            handler: 'CacheFirst',
            options: {
              cacheName: 'scene-art',
              expiration: { maxEntries: 40, maxAgeSeconds: 60 * 60 * 24 * 90 }
            }
          }
        ]
      }
    })
  ],
  server: {
    port: 5173,
    proxy: {
      '/api': {
        target: 'http://127.0.0.1:8000',
        changeOrigin: true
      },
      // Pre-generated sentence MP3s served from Laravel's public/audio
      '/audio': {
        target: 'http://127.0.0.1:8000',
        changeOrigin: true
      },
      // OpenMoji illustrations served from Laravel's public/images
      '/images': {
        target: 'http://127.0.0.1:8000',
        changeOrigin: true
      }
    }
  }
})
