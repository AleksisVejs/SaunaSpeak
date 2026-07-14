<script setup>
// "Continue with Google" - a full-page hop to the backend's OAuth redirect
// (not an XHR: Google must own the whole window for the consent screen).
// The browser timezone rides along so streaks are right from day one.
function go() {
  let tz = ''
  try {
    tz = Intl.DateTimeFormat().resolvedOptions().timeZone || ''
  } catch {
    // no timezone - the server falls back to app time
  }
  window.location.href = '/api/auth/google/redirect?tz=' + encodeURIComponent(tz)
}
</script>

<template>
  <div class="google-auth">
    <div class="divider" aria-hidden="true"><span>or</span></div>
    <button type="button" class="btn btn-ghost btn-block google-btn" @click="go">
      <svg class="g-logo" viewBox="0 0 48 48" width="18" height="18" aria-hidden="true">
        <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
        <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
        <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
        <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
      </svg>
      Continue with Google
    </button>
  </div>
</template>

<style scoped>
.google-auth { margin-top: 14px; }
.divider {
  display: flex; align-items: center; gap: 12px;
  color: var(--text-dim); font-size: 12.5px; margin-bottom: 14px;
}
.divider::before, .divider::after { content: ''; flex: 1; border-top: 1px solid var(--border, rgba(255, 255, 255, 0.12)); }
.google-btn { display: flex; align-items: center; justify-content: center; gap: 10px; }
.g-logo { flex: 0 0 auto; }
</style>
