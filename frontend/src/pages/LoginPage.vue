<script setup>
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import GoogleAuthButton from '../components/GoogleAuthButton.vue'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const email = ref('')
const password = ref('')
const showPassword = ref(false)
// The Google callback bounces failures back here with ?oauth=failed.
const error = ref(route.query.oauth === 'failed' ? 'Google sign-in didn\'t complete. Try again, or log in with your email.' : '')
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
    <div class="page-top">
      <router-link to="/" class="home-link">‹ Home</router-link>
    </div>

    <div class="hero">
      <router-link to="/" class="hero-home" title="Back to the home page">
        <img class="hero-logo" src="/logo-sm.png" alt="SaunaSpeak logo" />
        <h1>Welcome back</h1>
      </router-link>
      <p class="muted">The bench is warm and your streak is waiting.</p>
    </div>

    <form class="card" @submit.prevent="submit">
      <div v-if="error" class="error-msg" role="alert">{{ error }}</div>

      <GoogleAuthButton divider-text="or log in with email" />

      <div class="field">
        <label for="email">Email</label>
        <input
          id="email" v-model="email" type="email" required
          autocomplete="email" autofocus placeholder="you@example.com"
        />
      </div>
      <div class="field">
        <div class="label-row">
          <label for="password">Password</label>
          <router-link to="/forgot-password" class="forgot">Forgot it?</router-link>
        </div>
        <div class="pw-wrap">
          <input
            id="password" v-model="password" :type="showPassword ? 'text' : 'password'"
            required autocomplete="current-password" placeholder="••••••••"
          />
          <button
            type="button" class="pw-toggle"
            :aria-label="showPassword ? 'Hide password' : 'Show password'"
            @click="showPassword = !showPassword"
          >{{ showPassword ? 'Hide' : 'Show' }}</button>
        </div>
      </div>
      <button class="btn btn-primary btn-block" type="submit" :disabled="loading">
        {{ loading ? 'Logging in…' : 'Log in' }}
      </button>
    </form>

    <p class="muted switch">
      New here? <router-link to="/register">Create a free account</router-link>
    </p>
    <router-link to="/try" class="btn btn-ghost btn-block try-link">👀 Try a sentence first - no signup</router-link>
  </div>
</template>

<style scoped>
.auth-page { margin-top: 5vh; }
.page-top { margin-bottom: 8px; }
.home-link { color: var(--text-dim); font-size: 14px; font-weight: 600; }
.label-row { display: flex; align-items: baseline; justify-content: space-between; }
.forgot { font-size: 12.5px; font-weight: 600; color: var(--accent); }
.hero { text-align: center; margin-bottom: 28px; }
.hero-home { display: block; color: var(--text); }
.hero-logo { width: 76px; height: 76px; border-radius: 18px; margin-bottom: 10px; }
.hero h1 { font-size: 30px; margin-bottom: 6px; }
.switch { text-align: center; margin-top: 18px; }
.try-link { margin-top: 14px; }

.pw-wrap { position: relative; }
.pw-wrap input { width: 100%; padding-right: 62px; }
.pw-toggle {
  position: absolute; right: 8px; top: 50%; transform: translateY(-50%);
  background: none; border: none; cursor: pointer;
  color: var(--text-dim); font-size: 12.5px; font-weight: 700; padding: 6px;
}
.pw-toggle:hover { color: var(--accent); }
</style>
