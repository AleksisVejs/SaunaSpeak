<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const router = useRouter()
const auth = useAuthStore()

const email = ref('')
const password = ref('')
const error = ref('')
const loading = ref(false)

async function submit() {
  error.value = ''
  loading.value = true
  try {
    await auth.login({ email: email.value, password: password.value })
    router.push({ name: 'dashboard' })
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Login failed. Please try again.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="auth-page">
    <div class="hero">
      <img class="hero-logo" src="/logo-sm.png" alt="SaunaSpeak logo" />
      <h1>SaunaSpeak</h1>
      <p class="muted">Learn Finnish, one sauna session at a time.</p>
    </div>

    <form class="card" @submit.prevent="submit">
      <div v-if="error" class="error-msg">{{ error }}</div>
      <div class="field">
        <label for="email">Email</label>
        <input id="email" v-model="email" type="email" required autocomplete="email" placeholder="you@example.com" />
      </div>
      <div class="field">
        <label for="password">Password</label>
        <input id="password" v-model="password" type="password" required autocomplete="current-password" placeholder="••••••••" />
      </div>
      <button class="btn btn-primary btn-block" type="submit" :disabled="loading">
        {{ loading ? 'Logging in…' : 'Log in' }}
      </button>
    </form>

    <p class="muted switch">
      New here? <router-link to="/register">Create an account</router-link>
    </p>
    <router-link to="/try" class="btn btn-ghost btn-block try-link">👀 Try a sentence first - no signup</router-link>
  </div>
</template>

<style scoped>
.auth-page { margin-top: 8vh; }
.hero { text-align: center; margin-bottom: 28px; }
.hero-logo { width: 76px; height: 76px; border-radius: 18px; margin-bottom: 10px; }
.hero h1 { font-size: 30px; margin-bottom: 6px; }
.switch { text-align: center; margin-top: 18px; }
.try-link { margin-top: 14px; }
</style>
