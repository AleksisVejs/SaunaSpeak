import { ref } from 'vue'

// Module scope so the listener is registered as soon as the app boots -
// `beforeinstallprompt` often fires before any page component mounts.
const deferredPrompt = ref(null)

window.addEventListener('beforeinstallprompt', (e) => {
  e.preventDefault()
  deferredPrompt.value = e
})

window.addEventListener('appinstalled', () => {
  deferredPrompt.value = null
})

export function usePwaInstall() {
  async function install() {
    const evt = deferredPrompt.value
    if (!evt) return
    evt.prompt()
    await evt.userChoice
    deferredPrompt.value = null
  }

  return { installPrompt: deferredPrompt, install }
}
