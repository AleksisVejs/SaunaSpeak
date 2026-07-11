<script setup>
// Profile & settings: identity, stats, rank, learning preferences, appearance.
import { onMounted, ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import api from '../api'
import { useAuthStore } from '../stores/auth'
import { usePrefs } from '../composables/usePrefs'
import { useTheme } from '../composables/useTheme'

const router = useRouter()
const auth = useAuthStore()
const { prefs, savePrefs, dailyGoal, audioRate } = usePrefs()
const { theme, setTheme } = useTheme()

const loading = ref(true)
const insights = ref(null)
const premium = computed(() => auth.user?.is_premium !== false)

onMounted(async () => {
  try {
    if (!auth.user) await auth.fetchUser()
    // Weekly insights are Löyly+; a 402 just means we show the upsell row.
    try {
      const { data } = await api.get('/insights/week')
      insights.value = data
    } catch {
      insights.value = null
    }
  } finally {
    loading.value = false
  }
})

// Mini bar chart: last 7 days of reviews, scaled to the busiest day.
const weekBars = computed(() => {
  if (!insights.value) return []
  const byDate = Object.fromEntries((insights.value.by_day ?? []).map((d) => [d.date, d.count]))
  const max = Math.max(1, ...Object.values(byDate))
  return Array.from({ length: 7 }, (_, i) => {
    const d = new Date(Date.now() - (6 - i) * 86400000)
    const key = d.toISOString().slice(0, 10)
    return {
      key,
      label: d.toLocaleDateString('en', { weekday: 'narrow' }),
      count: byDate[key] ?? 0,
      pct: Math.round(((byDate[key] ?? 0) / max) * 100)
    }
  })
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

      <h3 class="section">This week</h3>
      <div v-if="insights" class="card week">
        <div class="week-stats">
          <div class="wstat"><span class="wv">{{ insights.reviews }}</span><span class="wl">reviews</span></div>
          <div class="wstat"><span class="wv">{{ insights.recall_pct ?? '–' }}<small v-if="insights.recall_pct !== null">%</small></span><span class="wl">recall</span></div>
          <div class="wstat"><span class="wv">{{ insights.new_sentences }}</span><span class="wl">new</span></div>
          <div class="wstat"><span class="wv">{{ insights.active_days }}/7</span><span class="wl">days</span></div>
        </div>
        <div class="week-bars">
          <div v-for="b in weekBars" :key="b.key" class="wb">
            <div class="wb-track"><div class="wb-fill" :style="{ height: b.pct + '%' }"></div></div>
            <span class="wb-label">{{ b.label }}</span>
          </div>
        </div>
        <p v-if="insights.reviews === 0" class="muted week-empty">No reviews yet this week - a Sauna Session fixes that. 🧖</p>
      </div>
      <router-link v-else to="/upgrade" class="card row-link">
        <span>📈 Weekly insights</span>
        <span class="chev">Löyly+ ›</span>
      </router-link>

      <h3 class="section">Subscription</h3>
      <router-link to="/upgrade" class="card row-link">
        <span>{{ premium ? '♨️ Löyly+ active' : 'Free plan' }}</span>
        <span class="chev">{{ premium ? 'Manage ›' : 'Upgrade ›' }}</span>
      </router-link>

      <template v-if="auth.user?.is_admin">
        <h3 class="section">Admin</h3>
        <router-link to="/admin" class="card row-link">
          <span>🛠 Admin panel</span>
          <span class="chev">Open ›</span>
        </router-link>
      </template>

      <h3 class="section">Audio speed</h3>
      <div class="card">
        <div class="rate-head">
          <span class="muted">All Finnish audio plays at</span>
          <b class="rate-value">{{ audioRate().toFixed(2).replace(/\.?0+$/, '') }}×</b>
        </div>
        <input
          class="rate-slider"
          type="range"
          min="0.5"
          max="2"
          step="0.25"
          :value="audioRate()"
          @input="savePrefs({ audioRate: Number($event.target.value) })"
        />
        <div class="rate-marks">
          <span class="mark-lo">0.5×</span>
          <span class="mark-one">1×</span>
          <span class="mark-hi">2×</span>
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

.week { display: flex; flex-direction: column; gap: 14px; }
.week-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; }
.wstat { display: flex; flex-direction: column; align-items: center; }
.wv { font-size: 20px; font-weight: 800; }
.wv small { font-size: 13px; }
.wl { font-size: 11px; color: var(--text-dim); }
.week-bars { display: flex; gap: 6px; align-items: flex-end; height: 56px; }
.wb { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 3px; height: 100%; }
.wb-track { flex: 1; width: 100%; display: flex; align-items: flex-end; background: var(--bg-soft); border-radius: 6px; overflow: hidden; }
.wb-fill { width: 100%; background: linear-gradient(180deg, var(--accent-2), var(--accent)); border-radius: 6px 6px 0 0; min-height: 2px; }
.wb-label { font-size: 10px; color: var(--text-faint); }
.week-empty { font-size: 13px; text-align: center; }

.rate-head { display: flex; align-items: baseline; justify-content: space-between; margin-bottom: 10px; }
.rate-value { color: var(--accent); font-size: 18px; }
.rate-slider {
  width: 100%;
  accent-color: var(--accent);
  cursor: pointer;
}
.rate-marks {
  position: relative;
  height: 16px;
  font-size: var(--text-xs);
  color: var(--text-faint);
  margin-top: 4px;
}
.rate-marks span { position: absolute; top: 0; }
.mark-lo { left: 0; }
/* 1× sits a third of the way along the linear 0.5–2 track */
.mark-one { left: calc((1 - 0.5) / (2 - 0.5) * 100%); transform: translateX(-50%); }
.mark-hi { right: 0; }

.row-link { display: flex; align-items: center; justify-content: space-between; color: var(--text); font-weight: 600; }
.row-link .chev { color: var(--accent); font-size: 14px; font-weight: 700; }

.logout { margin-top: 24px; }
.credits { text-align: center; font-size: 12px; color: var(--text-faint); margin-top: 18px; }
.credits a { color: var(--text-dim); text-decoration: underline; }
</style>
