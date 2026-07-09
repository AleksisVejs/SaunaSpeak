import { ref } from 'vue'

const STORAGE_KEY = 'ss_theme'

function resolveInitial() {
  const saved = localStorage.getItem(STORAGE_KEY)
  if (saved === 'light' || saved === 'dark') return saved
  // Respect the OS preference on first visit; default to dark (sauna dusk).
  if (window.matchMedia?.('(prefers-color-scheme: light)').matches) return 'light'
  return 'dark'
}

// Shared singleton state so every component sees the same theme.
const theme = ref(resolveInitial())

function apply(value) {
  document.documentElement.setAttribute('data-theme', value)
}

// Apply immediately on module load so there's no flash of the wrong theme.
apply(theme.value)

export function useTheme() {
  function setTheme(value) {
    theme.value = value
    localStorage.setItem(STORAGE_KEY, value)
    apply(value)
  }

  function toggleTheme() {
    setTheme(theme.value === 'dark' ? 'light' : 'dark')
  }

  return { theme, setTheme, toggleTheme }
}
