<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const router = useRouter()
const auth = useAuthStore()

const name = ref('')
const email = ref('')
const password = ref('')
const error = ref('')
const loading = ref(false)

async function submit() {
  error.value = ''
  loading.value = true
  try {
    await auth.register({ name: name.value, email: email.value, password: password.value })
    // New accounts go through the 2-minute intake before the dashboard.
    router.push({ name: 'onboarding' })
  } catch (e) {
    const errors = e.response?.data?.errors
    error.value = errors
      ? Object.values(errors).flat().join(' ')
      : e.response?.data?.message ?? 'Registration failed. Please try again.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="auth-page">
    <div class="hero">
      <div class="hero-icon">🧖</div>
      <h1>Join SaunaSpeak</h1>
      <p class="muted">Short daily sessions. Real Finnish sentences.</p>
    </div>

    <form class="card" @submit.prevent="submit">
      <div v-if="error" class="error-msg">{{ error }}</div>
      <div class="field">
        <label for="name">Name</label>
        <input id="name" v-model="name" type="text" required autocomplete="name" placeholder="Aino" />
      </div>
      <div class="field">
        <label for="email">Email</label>
        <input id="email" v-model="email" type="email" required autocomplete="email" placeholder="you@example.com" />
      </div>
      <div class="field">
        <label for="password">Password (min 8 characters)</label>
        <input id="password" v-model="password" type="password" required minlength="8" autocomplete="new-password" placeholder="••••••••" />
      </div>
      <button class="btn btn-primary btn-block" type="submit" :disabled="loading">
        {{ loading ? 'Creating account…' : 'Create account' }}
      </button>
    </form>

    <p class="muted switch">
      Already have an account? <router-link to="/login">Log in</router-link>
    </p>
  </div>
</template>

<style scoped>
.auth-page { margin-top: 8vh; }
.hero { text-align: center; margin-bottom: 28px; }
.hero-icon { font-size: 44px; margin-bottom: 8px; }
.hero h1 { font-size: 30px; margin-bottom: 6px; }
.switch { text-align: center; margin-top: 18px; }
</style>
