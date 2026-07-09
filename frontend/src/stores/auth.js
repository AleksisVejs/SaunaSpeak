import { defineStore } from 'pinia'
import api from '../api'

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
      const { data } = await api.post('/register', payload)
      this.setToken(data.token)
      this.user = data.user
    },

    async login(payload) {
      const { data } = await api.post('/login', payload)
      this.setToken(data.token)
      this.user = data.user
    },

    async fetchUser() {
      const { data } = await api.get('/user')
      this.user = data.user
      this.stats = data.stats
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
    }
  }
})
