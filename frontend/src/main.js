import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { registerSW } from 'virtual:pwa-register'
import App from './App.vue'
import router from './router'
import './style.css'
// Registers the beforeinstallprompt listener before the browser fires it.
import './composables/usePwaInstall'

const updateSW = registerSW({
  immediate: true,
  onNeedRefresh() {
    updateSW(true)
  },
  onRegisteredSW(_swUrl, registration) {
    // Catch updates deployed while the tab stays open.
    registration && setInterval(() => registration.update(), 60 * 60 * 1000)
  }
})

const app = createApp(App)
app.use(createPinia())
app.use(router)
app.mount('#app')
