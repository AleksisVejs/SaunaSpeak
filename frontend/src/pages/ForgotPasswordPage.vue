<script setup>
import { ref } from 'vue'
import { Inbox } from 'lucide-vue-next'
import api from '../api'

const email = ref('')
const sent = ref(false)
const error = ref('')
const loading = ref(false)

async function submit() {
  error.value = ''
  loading.value = true
  try {
    await api.post('/password/forgot', { email: email.value })
    sent.value = true
  } catch (e) {
    error.value = e.response?.status === 429
      ? 'Too many requests - wait a minute and try again.'
      : 'Something went wrong. Try again in a moment.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="auth-page">
    <div class="page-top">
      <router-link to="/" class="home-link">‹ Home</router-link>
    </div>

    <div class="hero">
      <img class="hero-vaino" src="/vaino-think.png" alt="Väinö thinking" />
      <h1>Forgot your password?</h1>
      <p class="muted">No hätä - we'll email you a link to pick a new one.</p>
    </div>

    <div v-if="sent" class="card sent">
      <p class="sent-title"><Inbox class="sent-ico" aria-hidden="true" /> Check your inbox</p>
      <p class="muted">
        If <b>{{ email }}</b> has an account, a reset link is on its way.
        The link works for 60 minutes.
      </p>
      <router-link to="/login" class="btn btn-ghost btn-block back-btn">Back to log in</router-link>
    </div>

    <form v-else class="card" @submit.prevent="submit">
      <div v-if="error" class="error-msg">{{ error }}</div>
      <div class="field">
        <label for="email">Email</label>
        <input id="email" v-model="email" type="email" required autocomplete="email" placeholder="you@example.com" />
      </div>
      <button class="btn btn-primary btn-block" type="submit" :disabled="loading">
        {{ loading ? 'Sending…' : 'Email me a reset link' }}
      </button>
    </form>

    <p class="muted switch">
      Remembered it after all? <router-link to="/login">Log in</router-link>
    </p>
  </div>
</template>

<style scoped>
.auth-page { margin-top: 6vh; }
.page-top { margin-bottom: 8px; }
.home-link { color: var(--text-dim); font-size: 14px; font-weight: 600; }
.hero { text-align: center; margin-bottom: 24px; }
.hero-vaino { width: 96px; height: 96px; margin-bottom: 6px; }
.hero h1 { font-size: 26px; margin-bottom: 6px; }
.switch { text-align: center; margin-top: 18px; }
.sent { text-align: center; }
.sent-title { font-weight: 800; font-size: 17px; margin-bottom: 8px; display: flex; align-items: center; gap: 7px; }
.sent-ico { width: 17px; height: 17px; color: var(--accent); flex-shrink: 0; }
.back-btn { margin-top: 16px; }
</style>
