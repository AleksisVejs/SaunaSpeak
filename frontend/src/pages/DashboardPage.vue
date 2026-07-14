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

// Sauna ranks - löyly levels earned with XP.
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

// The SRS schedule, made visible: a 7-day timeline of when sentences
// come back. Today = what's due right now; later days from stats.forecast.
const schedule = computed(() => {
  const byDate = Object.fromEntries((auth.stats?.forecast ?? []).map((r) => [r.date, r.count]))
  const fmt = new Intl.DateTimeFormat('en', { weekday: 'short' })

  const days = Array.from({ length: 7 }, (_, i) => {
    const d = new Date(Date.now() + i * 86400000)
    const key = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
    const count = i === 0 ? dueCount.value : (byDate[key] ?? 0)
    return { key, label: i === 0 ? 'Now' : fmt.format(d), count }
  })

  const max = Math.max(1, ...days.map((d) => d.count))
  return days.map((d) => ({ ...d, pct: Math.round((d.count / max) * 100) }))
})

const hasSchedule = computed(() => schedule.value.some((d) => d.count > 0))
</script>

<template>
  <div>
    <div v-if="loading" class="spinner"></div>

    <div v-else class="dashboard">
      <!-- greeting + compact stat chips -->
      <header class="top">
        <h2 class="greeting">Hei, {{ auth.user?.name }}! 👋</h2>
        <div class="chips">
          <span class="chip" title="XP — points earned from every completed exercise" aria-label="Experience points">
            <span class="chip-icon">⚡</span>{{ auth.user?.xp ?? 0 }}
            <span class="chip-label">XP</span>
          </span>
          <span class="chip" title="Streak — days in a row you've practiced" aria-label="Day streak">
            <span class="chip-icon">🔥</span>{{ auth.user?.streak ?? 0 }}
            <span class="chip-label">day streak</span>
          </span>
        </div>
      </header>

      <!-- rank progress -->
      <div class="card rank-card">
        <div class="rank-head">
          <span class="rank-icon">{{ rank.icon }}</span>
          <div class="rank-info">
            <p class="rank-title">{{ rank.title }}</p>
            <p class="muted rank-next">
              {{ rank.next ? `${rank.next.xp - (auth.user?.xp ?? 0)} XP to ${rank.next.title} ${rank.next.icon}` : 'Highest rank reached - legenda!' }}
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

      <!-- review schedule: when your sentences come back, and why -->
      <div v-if="hasSchedule" class="card schedule">
        <div class="schedule-head">
          <p class="schedule-title">
            <svg class="schedule-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true">
              <circle cx="12" cy="12" r="9" />
              <path d="M12 7v5l3.5 2" />
            </svg>
            Review schedule
          </p>
          <span class="schedule-sub muted">next 7 days</span>
        </div>
        <div class="schedule-days">
          <div
            v-for="d in schedule"
            :key="d.key"
            class="sday"
            :class="{ today: d.label === 'Now', empty: !d.count }"
          >
            <span class="sday-count">{{ d.count || '·' }}</span>
            <div class="sday-track"><div class="sday-fill" :style="{ height: d.pct + '%' }"></div></div>
            <span class="sday-label">{{ d.label }}</span>
          </div>
        </div>
        <p class="schedule-why muted">
          Each sentence returns right before you'd forget it - that timing is what makes it stick.
        </p>
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
  flex-wrap: wrap;
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
.chip-label {
  font-size: 10px;
  font-weight: 700;
  color: var(--text-dim);
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-left: 1px;
}

.rank-card { margin-bottom: 16px; }
.rank-head { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }
.rank-icon { font-size: 32px; }
.rank-title { font-weight: 800; font-size: 17px; }
.rank-next { font-size: 13px; margin-top: 2px; }

.session-btn { font-size: 18px; padding: 17px; }
.session-hint { text-align: center; margin: 10px 0 12px; }
.schedule { margin-bottom: 18px; }
.schedule-head { display: flex; align-items: baseline; justify-content: space-between; margin-bottom: 12px; }
.schedule-title { font-weight: 800; font-size: 14px; display: flex; align-items: center; gap: 7px; }
.schedule-icon { width: 16px; height: 16px; color: var(--accent); flex-shrink: 0; }
.schedule-sub { font-size: 11px; }
.schedule-days { display: flex; gap: 8px; align-items: flex-end; }
.sday { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 4px; }
.sday-count { font-size: 13px; font-weight: 800; color: var(--text); }
.sday.empty .sday-count { color: var(--text-faint); font-weight: 400; }
.sday.today .sday-count { color: var(--accent); }
.sday-track {
  width: 100%;
  height: 44px;
  display: flex;
  align-items: flex-end;
  background: var(--bg-soft);
  border-radius: 7px;
  overflow: hidden;
}
.sday-fill {
  width: 100%;
  min-height: 2px;
  border-radius: 7px 7px 0 0;
  background: linear-gradient(180deg, var(--accent-2), var(--accent));
  transition: height 0.4s ease;
}
.sday.empty .sday-fill { background: var(--border); }
.sday-label {
  font-size: 10px;
  font-weight: 600;
  color: var(--text-dim);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.sday.today .sday-label { color: var(--accent); }
.schedule-why { font-size: 12px; line-height: 1.5; margin-top: 12px; text-align: center; }
.install-btn { margin-bottom: 22px; font-size: 15px; }

.journey-head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  margin-bottom: 16px;
}
.journey-head h3 { font-size: 18px; }
</style>
