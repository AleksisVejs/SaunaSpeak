import { defineStore } from 'pinia'
import api from '../api'
import { usePrefs } from '../composables/usePrefs'

// The learner's clock: streaks and daily bonuses follow this, not server time.
function browserTimezone() {
  try {
    return Intl.DateTimeFormat().resolvedOptions().timeZone || null
  } catch {
    return null
  }
}

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    stats: null,
    token: localStorage.getItem('token')
  }),

  getters: {
    isLoggedIn: (s) => !!s.token
  },

  actions: {
    setToken(token) {
      this.token = token
      localStorage.setItem('token', token)
    },

    async register(payload) {
      const { data } = await api.post('/register', { ...payload, timezone: browserTimezone() })
      this.setToken(data.token)
      this.user = data.user
      // Funnel landmark for Umami (no-op in dev - script is domain-locked).
      window.umami?.track('register')
    },

    async login(payload) {
      const { data } = await api.post('/login', { ...payload, timezone: browserTimezone() })
      this.setToken(data.token)
      this.user = data.user
      // Adopt server prefs before the post-login redirect: the router guard
      // reads the onboarded flag, and without this a second device would be
      // bounced into the intake quiz again.
      usePrefs().adoptServerPrefs(data.user?.preferences)
    },

    async fetchUser() {
      const { data } = await api.get('/user')
      this.user = data.user
      this.stats = data.stats
      // Fresh device, existing account: pick up preferences from the server.
      usePrefs().adoptServerPrefs(data.user?.preferences)
    },

    async logout() {
      try {
        await api.post('/logout')
      } catch {
        // token may already be invalid; log out locally regardless
      }
      this.token = null
      this.user = null
      this.stats = null
      localStorage.removeItem('token')
      usePrefs().clearPrefs()
    }
  }
})
