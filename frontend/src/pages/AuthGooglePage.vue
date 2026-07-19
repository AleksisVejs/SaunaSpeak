<script setup>
// Landing spot for the Google OAuth callback: the backend redirects here
// with the Sanctum token in the URL fragment (#token=...&new=0|1). The
// fragment never reaches any server or log - this page moves it into
// localStorage and gets out of the way. An inline script in index.html
// strips the fragment (and stashes it in sessionStorage) before analytics
// loads, so the token never lands in Umami - we read the stash here first.
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const router = useRouter()
const auth = useAuthStore()
const failed = ref(false)

onMounted(async () => {
  // Prefer the sessionStorage stash set by index.html; fall back to the live
  // fragment in case that inline script didn't run (e.g. a stale cached shell).
  const stash = sessionStorage.getItem('oauth_hash')
  sessionStorage.removeItem('oauth_hash')
  const params = new URLSearchParams(stash ?? window.location.hash.slice(1))
  const token = params.get('token')
  const isNew = params.get('new') === '1'

  if (!token) {
    failed.value = true
    return
  }

  auth.setToken(token)
  try {
    // Pulls the account + adopts server prefs, so a returning learner on a
    // fresh device skips the intake quiz (same path as password login).
    await auth.fetchUser()
  } catch {
    // Drop the bad token locally only - an API /logout with it would 401
    // and the interceptor would hard-redirect before we can show anything.
    auth.token = null
    localStorage.removeItem('token')
    failed.value = true
    return
  }

  if (isNew) window.umami?.track('register')
  // replace(): keep the token fragment out of the back-button history.
  router.replace({ name: isNew ? 'onboarding' : 'dashboard' })
})
</script>

<template>
  <div class="oauth-landing">
    <template v-if="failed">
      <p class="error-msg">Google sign-in didn't complete.</p>
      <router-link to="/login" class="btn btn-primary">Back to log in</router-link>
    </template>
    <p v-else class="muted">Signing you in…</p>
  </div>
</template>

<style scoped>
.oauth-landing {
  min-height: 50vh; display: flex; flex-direction: column;
  align-items: center; justify-content: center; gap: 16px; text-align: center;
}
</style>
