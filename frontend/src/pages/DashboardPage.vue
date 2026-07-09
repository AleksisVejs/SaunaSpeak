<script setup>
import { onMounted, ref, computed } from 'vue'
import { useAuthStore } from '../stores/auth'
import { usePwaInstall } from '../composables/usePwaInstall'
import { usePrefs } from '../composables/usePrefs'
import LessonPath from '../components/LessonPath.vue'
import api from '../api'

const auth = useAuthStore()
const { installPrompt, install } = usePwaInstall()
const { dailyGoal } = usePrefs()

const lessons = ref([])
const loading = ref(true)

onMounted(async () => {
  try {
    const [, lessonsRes] = await Promise.all([auth.fetchUser(), api.get('/lessons')])
    lessons.value = lessonsRes.data.lessons
  } finally {
    loading.value = false
  }
})

// Sauna ranks — löyly levels earned with XP.
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
  RANKS.forEach((r, i) => {
    if (xp >= r.xp) idx = i
  })
  const current = RANKS[idx]
  const next = RANKS[idx + 1] ?? null
  const pct = next ? Math.round(((xp - current.xp) / (next.xp - current.xp)) * 100) : 100
  return { ...current, next, pct }
})

const dueCount = computed(() => auth.stats?.due_count ?? 0)

// The SRS schedule, made visible: reviews landing over the next 7 days.
const forecast = computed(() => {
  const rows = auth.stats?.forecast ?? []
  const fmt = new Intl.DateTimeFormat('en', { weekday: 'short' })
  const tomorrow = new Date(Date.now() + 86400000).toDateString()
  return rows.slice(0, 4).map((r) => {
    const d = new Date(`${r.date}T12:00:00`)
    return {
      label: d.toDateString() === tomorrow ? 'Tomorrow' : fmt.format(d),
      count: r.count
    }
  })
})
</script>

<template>
  <div>
    <div v-if="loading" class="spinner"></div>

    <div v-else class="dashboard">
      <!-- greeting + compact stat chips -->
      <header class="top">
        <h2 class="greeting">Hei, {{ auth.user?.name }}! 👋</h2>
        <div class="chips">
          <span class="chip"><span class="chip-icon">⚡</span>{{ auth.user?.xp ?? 0 }}</span>
          <span class="chip"><span class="chip-icon">🔥</span>{{ auth.user?.streak ?? 0 }}</span>
        </div>
      </header>

      <!-- rank progress -->
      <div class="card rank-card">
        <div class="rank-head">
          <span class="rank-icon">{{ rank.icon }}</span>
          <div class="rank-info">
            <p class="rank-title">{{ rank.title }}</p>
            <p class="muted rank-next">
              {{ rank.next ? `${rank.next.xp - (auth.user?.xp ?? 0)} XP to ${rank.next.title} ${rank.next.icon}` : 'Highest rank reached — legenda!' }}
            </p>
          </div>
        </div>
        <div class="progress-track">
          <div class="progress-fill" :style="{ width: rank.pct + '%' }"></div>
        </div>
      </div>

      <!-- hero: today's session -->
      <router-link to="/session" class="btn btn-primary btn-block session-btn">
        🧖 Start Sauna Session
      </router-link>
      <p class="muted session-hint">
        {{ dueCount ? `${dueCount} sentences due` : 'Fresh sentences are waiting' }} · daily goal {{ dailyGoal() }}
      </p>

      <!-- review forecast: when your sentences come back -->
      <div v-if="forecast.length" class="forecast">
        <span class="forecast-label">🗓 Coming back:</span>
        <span v-for="f in forecast" :key="f.label" class="forecast-chip">
          {{ f.label }} <b>{{ f.count }}</b>
        </span>
      </div>

      <button v-if="installPrompt" class="btn btn-ghost btn-block install-btn" @click="install">
        📲 Install SaunaSpeak on this device
      </button>

      <!-- the journey -->
      <div class="journey-head">
        <h3>Your journey</h3>
        <span class="muted">{{ auth.stats?.mastered_count ?? 0 }} / {{ auth.stats?.total_sentences ?? 0 }} mastered</span>
      </div>
      <LessonPath :lessons="lessons" />
    </div>
  </div>
</template>

<style scoped>
.top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 16px;
}
.greeting { font-size: 24px; }
.chips { display: flex; gap: 8px; }
.chip {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: var(--radius-pill);
  padding: 6px 12px;
  font-weight: 800;
  font-size: 15px;
}
.chip-icon { font-size: 15px; }

.rank-card { margin-bottom: 16px; }
.rank-head { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }
.rank-icon { font-size: 32px; }
.rank-title { font-weight: 800; font-size: 17px; }
.rank-next { font-size: 13px; margin-top: 2px; }

.session-btn { font-size: 18px; padding: 17px; }
.session-hint { text-align: center; margin: 10px 0 12px; }
.forecast {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: center;
  gap: 6px;
  margin-bottom: 18px;
}
.forecast-label { font-size: 12px; color: var(--text-faint); }
.forecast-chip {
  font-size: 12px;
  color: var(--text-dim);
  background: var(--bg-soft);
  border: 1px solid var(--border);
  border-radius: var(--radius-pill);
  padding: 3px 10px;
}
.forecast-chip b { color: var(--accent); }
.install-btn { margin-bottom: 22px; font-size: 15px; }

.journey-head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  margin-bottom: 16px;
}
.journey-head h3 { font-size: 18px; }
</style>
