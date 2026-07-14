<script setup>
import { ref } from 'vue'
import { useRoute } from 'vue-router'
import api from '../api'

const route = useRoute()

const email = ref(String(route.query.email ?? ''))
const password = ref('')
const done = ref(false)
const error = ref('')
const loading = ref(false)

// No token in the URL means someone typed the path by hand - send them to
// the request form instead of letting the submit fail cryptically.
const hasToken = !!route.query.token

async function submit() {
  error.value = ''
  loading.value = true
  try {
    await api.post('/password/reset', {
      token: route.query.token,
      email: email.value,
      password: password.value
    })
    done.value = true
  } catch (e) {
    const errors = e.response?.data?.errors
    error.value = errors
      ? Object.values(errors).flat().join(' ')
      : 'Could not reset the password. The link may have expired - request a new one.'
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
      <img class="hero-vaino" src="/vaino-flex.png" alt="Väinö flexing" />
      <h1>Choose a new password</h1>
    </div>

    <div v-if="!hasToken" class="card center">
      <p class="muted">This page needs the link from the reset email.</p>
      <router-link to="/forgot-password" class="btn btn-primary btn-block top-gap">Request a reset link</router-link>
    </div>

    <div v-else-if="done" class="card center">
      <p class="done-title">✅ Password updated</p>
      <p class="muted">Your streak and progress are exactly where you left them.</p>
      <router-link to="/login" class="btn btn-primary btn-block top-gap">Log in</router-link>
    </div>

    <form v-else class="card" @submit.prevent="submit">
      <div v-if="error" class="error-msg">{{ error }}</div>
      <div class="field">
        <label for="email">Email</label>
        <input id="email" v-model="email" type="email" required autocomplete="email" placeholder="you@example.com" />
      </div>
      <div class="field">
        <label for="password">New password (min 8 characters)</label>
        <input id="password" v-model="password" type="password" required minlength="8" autocomplete="new-password" placeholder="••••••••" />
      </div>
      <button class="btn btn-primary btn-block" type="submit" :disabled="loading">
        {{ loading ? 'Saving…' : 'Save new password' }}
      </button>
    </form>
  </div>
</template>

<style scoped>
.auth-page { margin-top: 6vh; }
.page-top { margin-bottom: 8px; }
.home-link { color: var(--text-dim); font-size: 14px; font-weight: 600; }
.hero { text-align: center; margin-bottom: 24px; }
.hero-vaino { width: 96px; height: 96px; margin-bottom: 6px; }
.hero h1 { font-size: 26px; }
.center { text-align: center; }
.done-title { font-weight: 800; font-size: 17px; margin-bottom: 8px; }
.top-gap { margin-top: 16px; }
</style>
