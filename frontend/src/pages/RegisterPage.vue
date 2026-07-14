<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import GoogleAuthButton from '../components/GoogleAuthButton.vue'

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
    <div class="page-top">
      <router-link to="/" class="home-link">‹ Home</router-link>
    </div>

    <div class="hero">
      <router-link to="/" class="hero-home" title="Back to the home page">
        <img class="hero-vaino" src="/vaino-wave.png" alt="Väinö waving hello" />
        <h1>Join SaunaSpeak</h1>
      </router-link>
      <p class="muted">Väinö's saving you a seat on the bench. Short daily sessions, real spoken Finnish.</p>
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
      <GoogleAuthButton />
      <p class="muted consent">
        By creating an account you agree to the
        <router-link to="/terms">terms</router-link> and
        <router-link to="/privacy">privacy policy</router-link>.
      </p>
    </form>

    <p class="muted switch">
      Already have an account? <router-link to="/login">Log in</router-link>
    </p>
  </div>
</template>

<style scoped>
.auth-page { margin-top: 5vh; }
.page-top { margin-bottom: 8px; }
.home-link { color: var(--text-dim); font-size: 14px; font-weight: 600; }
.consent { font-size: 12.5px; text-align: center; margin-top: 12px; line-height: 1.5; }
.consent a { color: var(--accent); text-decoration: underline; text-underline-offset: 2px; }
.hero { text-align: center; margin-bottom: 28px; }
.hero-home { display: block; color: var(--text); }
.hero-vaino { width: 110px; height: 110px; margin-bottom: 6px; }
.hero h1 { font-size: 30px; margin-bottom: 6px; }
.switch { text-align: center; margin-top: 18px; }
</style>
