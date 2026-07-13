<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from './api'
import { useAuthStore } from './stores/auth'
import { useTheme } from './composables/useTheme'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const { theme, toggleTheme } = useTheme()

// Email verification nudge: encouraged, never blocking. Hidden on the
// full-bleed scenes (chat) where a banner would break the illusion.
const resendState = ref('idle') // idle | sending | sent | error
const needsVerification = computed(() =>
  auth.user && !auth.user.email_verified_at && !route.meta.full
)

async function resendVerification() {
  resendState.value = 'sending'
  try {
    await api.post('/email/resend')
    resendState.value = 'sent'
  } catch {
    resendState.value = 'error'
  }
}

// Landing back from the mail link (?verified=1): refresh the user so the
// nudge disappears, and celebrate briefly.
const justVerified = ref(false)
watch(
  () => route.query.verified,
  (v) => {
    if (v === '1') {
      justVerified.value = true
      auth.fetchUser()
      router.replace({ query: {} })
      setTimeout(() => (justVerified.value = false), 6000)
    }
  },
  { immediate: true }
)

// Full-focus routes hide the shell chrome so learners aren't distracted.
const FOCUS_ROUTES = ['session', 'onboarding', 'try', 'words-review', 'checkpoint']

const showShell = computed(() => auth.isLoggedIn && !FOCUS_ROUTES.includes(route.name))

const navItems = [
  { name: 'dashboard', to: '/dashboard', icon: '🧭', label: 'Learn' },
  { name: 'chat', to: '/chat', icon: '💬', label: 'Chat' },
  { name: 'scenarios', to: '/scenarios', icon: '🎭', label: 'Situations' },
  { name: 'words', to: '/words', icon: '⭐', label: 'Words' },
  { name: 'profile', to: '/profile', icon: '🧖', label: 'Profile' }
]

async function logout() {
  await auth.logout()
  router.push({ name: 'login' })
}
</script>

<template>
  <div class="app-shell" :class="{ 'app-shell--chrome': showShell, 'app-shell--full': route.meta.full }">
    <!-- Desktop sidebar (hidden on mobile) -->
    <aside v-if="showShell" class="sidebar">
      <router-link to="/dashboard" class="brand">
        <img class="brand-logo" src="/logo-sm.png" alt="" />
        <span class="brand-name">SaunaSpeak</span>
      </router-link>

      <nav class="sidebar-nav">
        <router-link
          v-for="item in navItems"
          :key="item.name"
          :to="item.to"
          class="nav-link"
          :class="{ active: route.name === item.name }"
        >
          <span class="nav-icon">{{ item.icon }}</span>
          <span class="nav-label">{{ item.label }}</span>
        </router-link>
      </nav>

      <div class="sidebar-foot">
        <button class="foot-btn" @click="toggleTheme" :title="theme === 'dark' ? 'Light mode' : 'Dark mode'">
          <span class="nav-icon">{{ theme === 'dark' ? '☀️' : '🌙' }}</span>
          <span class="nav-label">{{ theme === 'dark' ? 'Light' : 'Dark' }}</span>
        </button>
        <button class="foot-btn" @click="logout">
          <span class="nav-icon">↩︎</span>
          <span class="nav-label">Log out</span>
        </button>
      </div>
    </aside>

    <main class="content" :class="{ 'content--wide': route.meta.wide }">
      <div v-if="justVerified" class="verify-banner verified">
        ✅ Email confirmed - kiitos!
      </div>
      <div v-else-if="showShell && needsVerification" class="verify-banner">
        <span class="vb-text">📧 Confirm your email - we sent a link to <b>{{ auth.user.email }}</b></span>
        <button class="vb-btn" :disabled="resendState === 'sending' || resendState === 'sent'" @click="resendVerification">
          {{ resendState === 'sent' ? 'Sent!' : resendState === 'sending' ? 'Sending…' : resendState === 'error' ? 'Try again' : 'Resend' }}
        </button>
      </div>

      <router-view v-slot="{ Component }">
        <component :is="Component" />
      </router-view>
    </main>

    <!-- Mobile bottom tab bar -->
    <nav v-if="showShell" class="tabbar">
      <router-link
        v-for="item in navItems"
        :key="item.name"
        :to="item.to"
        class="tab"
        :class="{ active: route.name === item.name }"
      >
        <span class="tab-icon">{{ item.icon }}</span>
        <span class="tab-label">{{ item.label }}</span>
      </router-link>
      <button class="tab" @click="toggleTheme">
        <span class="tab-icon">{{ theme === 'dark' ? '☀️' : '🌙' }}</span>
        <span class="tab-label">Theme</span>
      </button>
    </nav>
  </div>
</template>

<style scoped>
.app-shell {
  min-height: 100vh;
}

.verify-banner {
  display: flex;
  align-items: center;
  gap: 10px;
  justify-content: space-between;
  background: var(--accent-soft);
  border: 1px solid rgba(245, 158, 11, 0.35);
  border-radius: var(--radius-sm);
  padding: 10px 14px;
  margin-bottom: 14px;
  font-size: 13.5px;
  line-height: 1.4;
}
.verify-banner.verified { background: var(--green-soft); border-color: var(--green); font-weight: 700; }
.vb-text { min-width: 0; overflow-wrap: anywhere; }
.vb-btn {
  flex-shrink: 0;
  background: none;
  border: 1px solid var(--accent);
  color: var(--accent);
  border-radius: var(--radius-pill);
  padding: 6px 14px;
  font-family: inherit;
  font-size: 12.5px;
  font-weight: 700;
  cursor: pointer;
}
.vb-btn:disabled { opacity: 0.7; cursor: default; }

.content {
  width: 100%;
  max-width: var(--content-max);
  margin: 0 auto;
  padding: var(--space-5) var(--space-4) calc(var(--space-6) + env(safe-area-inset-bottom));
  display: flex;
  flex-direction: column;
}
/* When the mobile tab bar is present, keep content clear of it. */
.app-shell--chrome .content {
  padding-bottom: calc(84px + env(safe-area-inset-bottom));
}

/* Fullscreen scene routes (chat): the page fills the shell edge-to-edge and
   handles its own safe areas / tab-bar clearance. */
.app-shell--full .content,
.app-shell--chrome.app-shell--full .content {
  max-width: none;
  padding: 0;
}

/* ---------- sidebar (desktop) ---------- */
.sidebar { display: none; }

.brand {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 20px;
  font-weight: 800;
  color: var(--text);
  letter-spacing: -0.02em;
}
.brand-logo { width: 34px; height: 34px; border-radius: 9px; }

.nav-link,
.foot-btn {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 11px 12px;
  border-radius: var(--radius-sm);
  color: var(--text-dim);
  font-size: 15px;
  font-weight: 600;
  font-family: inherit;
  background: none;
  border: none;
  cursor: pointer;
  width: 100%;
  text-align: left;
  transition: background 0.15s ease, color 0.15s ease;
}
.nav-link:hover,
.foot-btn:hover { background: var(--card); color: var(--text); }
.nav-link.active { background: var(--accent-soft); color: var(--accent); }
.nav-icon { font-size: 18px; width: 22px; text-align: center; }

/* ---------- tab bar (mobile) ---------- */
.tabbar {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  display: flex;
  justify-content: space-around;
  background: color-mix(in srgb, var(--card) 92%, transparent);
  backdrop-filter: blur(12px);
  border-top: 1px solid var(--border);
  padding: 8px 8px calc(8px + env(safe-area-inset-bottom));
  z-index: 40;
}
.tab {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 3px;
  flex: 1;
  padding: 4px 0;
  background: none;
  border: none;
  color: var(--text-dim);
  font-family: inherit;
  font-size: 11px;
  font-weight: 600;
  cursor: pointer;
}
.tab.active { color: var(--accent); }
.tab-icon { font-size: 20px; }

/* ---------- desktop layout ---------- */
@media (min-width: 900px) {
  .app-shell--chrome {
    display: grid;
    grid-template-columns: var(--nav-width) 1fr;
    max-width: var(--shell-max);
    margin: 0 auto;
    gap: var(--space-6);
    padding: 0 var(--space-6);
  }
  .app-shell--chrome .sidebar {
    display: flex;
    flex-direction: column;
    gap: var(--space-4);
    position: sticky;
    top: 0;
    height: 100vh;
    padding: var(--space-6) 0;
  }
  .sidebar-nav {
    display: flex;
    flex-direction: column;
    gap: 4px;
    margin-top: var(--space-4);
  }
  .sidebar-foot {
    margin-top: auto;
    display: flex;
    flex-direction: column;
    gap: 4px;
  }
  .app-shell--chrome .content {
    padding: var(--space-8) 0 var(--space-10);
    max-width: 640px;
  }
  /* scene pages (chat) get room to breathe */
  .app-shell--chrome .content--wide { max-width: 980px; }
  /* fullscreen routes: the scene runs to the viewport edges beside the sidebar */
  .app-shell--chrome.app-shell--full {
    max-width: none;
    padding-right: 0;
  }
  .app-shell--chrome.app-shell--full .content {
    max-width: none;
    padding: 0;
  }
  .app-shell--chrome .tabbar { display: none; }
}
</style>
