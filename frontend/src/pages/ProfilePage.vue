<script setup>
// Profile & settings: identity, stats, rank, learning preferences, appearance.
import { onMounted, ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { usePrefs } from '../composables/usePrefs'
import { useTheme } from '../composables/useTheme'

const router = useRouter()
const auth = useAuthStore()
const { prefs, savePrefs, dailyGoal } = usePrefs()
const { theme, setTheme } = useTheme()

const loading = ref(true)

onMounted(async () => {
  try {
    if (!auth.user) await auth.fetchUser()
  } finally {
    loading.value = false
  }
})

const RANKS = [
  { xp: 0, title: 'Kylmä Kiuas', icon: '🪨' },
  { xp: 150, title: 'Ensilöyly', icon: '💧' },
  { xp: 400, title: 'Löylynheittäjä', icon: '♨️' },
  { xp: 800, title: 'Lauteiden Vakio', icon: '🧖' },
  { xp: 1400, title: 'Löylymestari', icon: '🔥' },
  { xp: 2200, title: 'Saunalegenda', icon: '👑' }
]

const rank = computed(() => {
  const xp = auth.user?.xp ?? 0
  let idx = 0
  RANKS.forEach((r, i) => { if (xp >= r.xp) idx = i })
  return RANKS[idx]
})

const GOAL_LABELS = { move: 'Moving to Finland', travel: 'Travel & visits', family: 'Family & friends', casual: 'Just curious' }
const TIME_OPTIONS = [{ v: 2, l: '2 min' }, { v: 5, l: '5 min' }, { v: 15, l: '15 min' }]

function setMinutes(m) {
  savePrefs({ minutes: m })
}

async function logout() {
  await auth.logout()
  router.push({ name: 'login' })
}
</script>

<template>
  <div>
    <div v-if="loading" class="spinner"></div>

    <div v-else class="profile">
      <div class="identity">
        <div class="avatar">{{ rank.icon }}</div>
        <div>
          <h2>{{ auth.user?.name }}</h2>
          <p class="muted">{{ rank.title }}</p>
        </div>
      </div>

      <div class="stat-grid">
        <div class="card stat"><span class="v">{{ auth.user?.xp ?? 0 }}</span><span class="l">XP</span></div>
        <div class="card stat"><span class="v">{{ auth.user?.streak ?? 0 }}</span><span class="l">day streak</span></div>
        <div class="card stat"><span class="v">{{ auth.stats?.mastered_count ?? 0 }}</span><span class="l">mastered</span></div>
      </div>

      <h3 class="section">Daily goal</h3>
      <div class="card">
        <p class="muted goal-note">Currently aiming for {{ dailyGoal() }} sentences a day.</p>
        <div class="seg">
          <button
            v-for="t in TIME_OPTIONS"
            :key="t.v"
            :class="{ on: prefs.minutes === t.v }"
            @click="setMinutes(t.v)"
          >{{ t.l }}</button>
        </div>
      </div>

      <h3 class="section">Learning goal</h3>
      <router-link to="/onboarding" class="card row-link">
        <span>{{ GOAL_LABELS[prefs.goal] || 'Set your goal' }}</span>
        <span class="chev">Redo intake ›</span>
      </router-link>

      <h3 class="section">Appearance</h3>
      <div class="card">
        <div class="seg">
          <button :class="{ on: theme === 'dark' }" @click="setTheme('dark')">🌙 Dark</button>
          <button :class="{ on: theme === 'light' }" @click="setTheme('light')">☀️ Light</button>
        </div>
      </div>

      <button class="btn btn-ghost btn-block logout" @click="logout">Log out</button>

      <p class="credits">
        Illustrations: <a href="https://openmoji.org" target="_blank" rel="noopener">OpenMoji</a> (CC BY-SA 4.0)
      </p>
    </div>
  </div>
</template>

<style scoped>
.identity { display: flex; align-items: center; gap: 14px; margin-bottom: 18px; }
.avatar {
  width: 60px; height: 60px; border-radius: 50%;
  display: grid; place-items: center; font-size: 30px;
  background: var(--accent-soft); border: 1px solid var(--border);
}
.identity h2 { font-size: 22px; }

.stat-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 8px; }
.stat { display: flex; flex-direction: column; align-items: center; padding: 16px 8px; }
.stat .v { font-size: 24px; font-weight: 800; }
.stat .l { font-size: 12px; color: var(--text-dim); }

.section { font-size: 15px; margin: 22px 0 10px; }
.goal-note { margin-bottom: 12px; }
.seg { display: flex; gap: 8px; }
.seg button {
  flex: 1;
  background: var(--bg-soft);
  border: 1px solid var(--border);
  color: var(--text-dim);
  border-radius: var(--radius-sm);
  padding: 11px;
  font-family: inherit;
  font-weight: 700;
  font-size: 14px;
  cursor: pointer;
  transition: all 0.15s ease;
}
.seg button.on { border-color: var(--accent); color: var(--accent); background: var(--accent-soft); }

.row-link { display: flex; align-items: center; justify-content: space-between; color: var(--text); font-weight: 600; }
.row-link .chev { color: var(--accent); font-size: 14px; font-weight: 700; }

.logout { margin-top: 24px; }
.credits { text-align: center; font-size: 12px; color: var(--text-faint); margin-top: 18px; }
.credits a { color: var(--text-dim); text-decoration: underline; }
</style>
