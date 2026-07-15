<script setup>
import { onMounted, ref, computed } from 'vue'
import { useAuthStore } from '../stores/auth'
import { usePwaInstall } from '../composables/usePwaInstall'
import { usePrefs } from '../composables/usePrefs'
import LessonPath from '../components/LessonPath.vue'
import { rankFor } from '../utils/ranks'
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

const rank = computed(() => rankFor(auth.user?.xp ?? 0))

// Streak repair: relight a recently broken streak for XP.
// Mirrors SessionController::STREAK_REPAIR_COST.
const REPAIR_COST = 200
const repairing = ref(false)
const repairError = ref('')

async function repairStreak() {
  if (repairing.value) return
  repairing.value = true
  repairError.value = ''
  try {
    const { data } = await api.post('/streak/repair')
    auth.user = data.user
  } catch (e) {
    repairError.value = e.response?.data?.message ?? 'Repair failed. Try again.'
  } finally {
    repairing.value = false
  }
}

const streakTitle = computed(() => {
  const base = "Streak — days in a row you've practiced"
  const n = auth.user?.streak_freezes ?? 0
  return n ? `${base} · ${n} freeze${n > 1 ? 's' : ''} banked (a freeze auto-saves one missed day)` : base
})

const dueCount = computed(() => auth.stats?.due_count ?? 0)

// Today's goal, made visible: reviews done today against the daily goal
// picked at onboarding. A visibly closing gap is what pulls a session in
// (goal-gradient effect) - the number alone never did.
const goal = computed(() => dailyGoal())
const todayDone = computed(() => auth.stats?.reviews_today ?? 0)
const goalPct = computed(() => Math.min(100, Math.round((todayDone.value / Math.max(1, goal.value)) * 100)))
const goalMet = computed(() => todayDone.value >= goal.value)
// SVG ring: r=10 → circumference 2π·10 ≈ 62.83
const RING_C = 62.83
const ringOffset = computed(() => RING_C * (1 - goalPct.value / 100))

// The next stop on the path: first unlocked lesson that isn't mastered.
// Mirrors LessonPath's rule, including test-out unlocks via checkpoints.
const nextLesson = computed(() => {
  const ls = lessons.value
  for (let i = 0; i < ls.length; i++) {
    const prev = ls[i - 1]
    const unlocked = i === 0
      || (prev && ((prev.started_count ?? prev.mastered_count) > 0 || auth.user?.checkpoints?.[prev.level]))
    if (!unlocked) break
    const mastered = ls[i].sentences_count > 0 && ls[i].mastered_count >= ls[i].sentences_count
    // Levels tested out of stay open but aren't the path forward.
    if (!mastered && !auth.user?.checkpoints?.[ls[i].level]) return ls[i]
  }
  return null
})

// Past 7 days of practice (from stats.activity, oldest → today).
const weekDays = computed(() => {
  const rows = auth.stats?.activity ?? []
  const fmt = new Intl.DateTimeFormat('en', { weekday: 'short' })
  return rows.map((r, i) => ({
    ...r,
    label: i === rows.length - 1 ? 'Today' : fmt.format(new Date(`${r.date}T12:00:00`)),
    isToday: i === rows.length - 1
  }))
})
const hasActivity = computed(() => weekDays.value.some((d) => d.count > 0))

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
const showWeek = computed(() => hasActivity.value || hasSchedule.value)
</script>

<template>
  <div>
    <!-- skeleton mirrors the real layout so nothing jumps on load -->
    <div v-if="loading" class="dashboard" aria-busy="true">
      <div class="skel skel-greeting"></div>
      <div class="skel skel-btn"></div>
      <div class="skel skel-line"></div>
      <div class="skel skel-card"></div>
      <div class="skel skel-card tall"></div>
    </div>

    <div v-else class="dashboard">
      <!-- greeting + compact stat chips -->
      <header class="top">
        <h2 class="greeting">Hei, {{ auth.user?.name }}! 👋</h2>
        <div class="chips">
          <span class="chip" title="XP — points earned from every completed exercise" aria-label="Experience points">
            <span class="chip-icon">⚡</span>{{ auth.user?.xp ?? 0 }}
            <span class="chip-label">XP</span>
          </span>
          <span class="chip" :title="streakTitle" aria-label="Day streak">
            <span class="chip-icon">🔥</span>{{ auth.user?.streak ?? 0 }}
            <span class="chip-label">day streak</span>
            <span v-if="auth.user?.streak_freezes" class="chip-freeze">❄️{{ auth.user.streak_freezes }}</span>
          </span>
        </div>
      </header>

      <!-- hero: today's session, with today's goal on the button -->
      <router-link to="/session" class="btn btn-primary btn-block session-btn">
        <span>🧖 Start Sauna Session</span>
        <svg
          class="goal-ring"
          :class="{ met: goalMet }"
          viewBox="0 0 24 24"
          role="img"
          :aria-label="`Today: ${todayDone} of ${goal} reviews done`"
        >
          <circle class="ring-track" cx="12" cy="12" r="10" />
          <circle
            class="ring-fill"
            cx="12"
            cy="12"
            r="10"
            :stroke-dasharray="RING_C"
            :stroke-dashoffset="ringOffset"
          />
          <text v-if="goalMet" x="12" y="16.2" class="ring-check">✓</text>
        </svg>
      </router-link>
      <p class="muted session-hint">
        <template v-if="nextLesson">Next up: <b class="hint-lesson">{{ nextLesson.title }}</b> ({{ nextLesson.level }}) · </template>
        <template v-if="dueCount">{{ dueCount }} due · </template>
        today {{ todayDone }}/{{ goal }}{{ goalMet ? ' — goal met! 🔥' : '' }}
      </p>

      <!-- a recently broken streak can be relit for XP -->
      <div v-if="auth.user?.streak_repairable" class="card repair-card">
        <div class="repair-info">
          <p class="repair-title">🥶 Your {{ auth.user.broken_streak }}-day streak went cold</p>
          <p class="repair-sub muted">Relight it within 3 days and the chain continues like you never missed.</p>
          <p v-if="repairError" class="repair-error">{{ repairError }}</p>
        </div>
        <button
          class="btn btn-primary repair-btn"
          :disabled="repairing || (auth.user?.xp ?? 0) < REPAIR_COST"
          @click="repairStreak"
        >
          {{ (auth.user?.xp ?? 0) < REPAIR_COST ? `Need ${REPAIR_COST} XP` : `🔥 Relight — ${REPAIR_COST} XP` }}
        </button>
      </div>

      <!-- rank: one slim strip instead of a full card -->
      <div class="card rank-strip" :title="`${rank.title} — sauna rank grows with XP`">
        <span class="rank-icon">{{ rank.icon }}</span>
        <div class="rank-mid">
          <div class="rank-row">
            <span class="rank-title">{{ rank.title }}</span>
            <span class="rank-next muted">
              {{ rank.next ? `${rank.next.xp - (auth.user?.xp ?? 0)} XP to ${rank.next.icon}` : 'legenda!' }}
            </span>
          </div>
          <div class="progress-track slim">
            <div class="progress-fill" :style="{ width: rank.pct + '%' }"></div>
          </div>
        </div>
      </div>

      <!-- your week: practice behind you, reviews ahead of you -->
      <div v-if="showWeek" class="card week">
        <template v-if="hasActivity">
          <div class="week-head">
            <p class="week-title">
              <svg class="week-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true">
                <path d="M4 13l4 4L20 6" />
              </svg>
              Past 7 days
            </p>
          </div>
          <div class="week-days">
            <div
              v-for="d in weekDays"
              :key="d.date"
              class="wday"
              :class="{ done: d.count > 0, today: d.isToday }"
              role="img"
              :aria-label="`${d.label}: ${d.count} reviews`"
              :title="`${d.label}: ${d.count} reviews`"
            >
              <span class="wday-dot">{{ d.count > 0 ? '✓' : '·' }}</span>
              <span class="wday-label">{{ d.label }}</span>
            </div>
          </div>
          <div v-if="hasSchedule" class="week-divider"></div>
        </template>

        <template v-if="hasSchedule">
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
              role="img"
              :aria-label="`${d.label}: ${d.count} reviews due`"
            >
              <span class="sday-count">{{ d.count || '·' }}</span>
              <div class="sday-track"><div class="sday-fill" :style="{ height: d.pct + '%' }"></div></div>
              <span class="sday-label">{{ d.label }}</span>
            </div>
          </div>
          <p class="schedule-why muted">
            Each sentence returns right before you'd forget it - that timing is what makes it stick.
          </p>
        </template>
      </div>

      <!-- the rest of the sauna: talk, roleplay, your own words -->
      <div class="quick-row">
        <router-link to="/chat" class="quick">
          <span class="quick-icon">💬</span>
          <span class="quick-name">Sauna Chat</span>
        </router-link>
        <router-link to="/scenarios" class="quick">
          <span class="quick-icon">🎭</span>
          <span class="quick-name">Situations</span>
        </router-link>
        <router-link to="/words" class="quick">
          <span class="quick-icon">📚</span>
          <span class="quick-name">Word bank</span>
          <span v-if="auth.stats?.words_due" class="quick-badge">{{ auth.stats.words_due }} due</span>
        </router-link>
      </div>

      <button v-if="installPrompt" class="btn btn-ghost btn-block install-btn" @click="install">
        📲 Install SaunaSpeak on this device
      </button>

      <!-- the journey -->
      <div class="journey-head">
        <h3>Your journey</h3>
        <span class="muted">{{ auth.stats?.mastered_count ?? 0 }} / {{ auth.stats?.total_sentences ?? 0 }} sentences mastered</span>
      </div>
      <LessonPath :lessons="lessons" />
    </div>
  </div>
</template>

<style scoped>
/* ---- skeleton ---- */
.skel {
  border-radius: var(--radius);
  background: linear-gradient(90deg, var(--bg-soft) 25%, var(--card) 50%, var(--bg-soft) 75%);
  background-size: 200% 100%;
  animation: shimmer 1.2s linear infinite;
}
@keyframes shimmer {
  to { background-position: -200% 0; }
}
.skel-greeting { height: 30px; width: 55%; margin-bottom: 18px; }
.skel-btn { height: 58px; margin-bottom: 10px; }
.skel-line { height: 14px; width: 70%; margin: 0 auto 16px; border-radius: 99px; }
.skel-card { height: 64px; margin-bottom: 14px; }
.skel-card.tall { height: 180px; }

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
.chip-freeze {
  font-size: 11px;
  font-weight: 700;
  color: var(--text-dim);
  padding-left: 7px;
  margin-left: 3px;
  border-left: 1px solid var(--border);
}

/* ---- hero ---- */
.session-btn {
  position: relative;
  font-size: 18px;
  padding: 17px 52px 17px 17px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.goal-ring {
  position: absolute;
  right: 15px;
  top: 50%;
  transform: translateY(-50%);
  width: 28px;
  height: 28px;
}
.ring-track,
.ring-fill {
  fill: none;
  stroke-width: 3;
}
.ring-track { stroke: rgba(0, 0, 0, 0.18); }
.ring-fill {
  stroke: currentColor;
  stroke-linecap: round;
  transform: rotate(-90deg);
  transform-origin: center;
  transition: stroke-dashoffset 0.5s ease;
}
.goal-ring.met .ring-fill { stroke: var(--green); }
.ring-check {
  font-size: 12px;
  font-weight: 800;
  fill: currentColor;
  text-anchor: middle;
}
.session-hint { text-align: center; margin: 10px 0 14px; }
.hint-lesson { color: var(--text); font-weight: 700; }

.repair-card {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 14px;
  border-color: var(--accent-2);
}
.repair-title { font-weight: 800; font-size: 15px; }
.repair-sub { font-size: 13px; margin-top: 2px; }
.repair-error { font-size: 13px; color: var(--red); margin-top: 4px; }
.repair-btn { flex-shrink: 0; white-space: nowrap; }

/* ---- slim rank strip ---- */
.rank-strip {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 14px;
  margin-bottom: 14px;
}
.rank-icon { font-size: 24px; flex-shrink: 0; }
.rank-mid { flex: 1; min-width: 0; }
.rank-row {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 10px;
  margin-bottom: 6px;
}
.rank-title { font-weight: 800; font-size: 14px; }
.rank-next { font-size: 12px; white-space: nowrap; }
.progress-track.slim { height: 6px; }

/* ---- week card ---- */
.week { margin-bottom: 14px; }
.week-head { display: flex; align-items: baseline; justify-content: space-between; margin-bottom: 10px; }
.week-title,
.schedule-title { font-weight: 800; font-size: 14px; display: flex; align-items: center; gap: 7px; }
.week-icon,
.schedule-icon { width: 16px; height: 16px; color: var(--accent); flex-shrink: 0; }
.week-days { display: flex; gap: 8px; }
.wday {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
}
.wday-dot {
  width: 26px;
  height: 26px;
  display: grid;
  place-items: center;
  border-radius: 50%;
  font-size: 13px;
  font-weight: 800;
  background: var(--bg-soft);
  color: var(--text-faint);
  border: 1.5px solid var(--border);
}
.wday.done .wday-dot {
  background: var(--green-soft);
  color: var(--green);
  border-color: var(--green);
}
.wday.today .wday-dot { border-color: var(--accent); }
.wday.today.done .wday-dot { border-color: var(--green); }
.wday-label {
  font-size: 10px;
  font-weight: 600;
  color: var(--text-dim);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.wday.today .wday-label { color: var(--accent); }
.week-divider { height: 1px; background: var(--border); margin: 14px 0; }

.schedule-head { display: flex; align-items: baseline; justify-content: space-between; margin-bottom: 12px; }
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

/* ---- quick actions ---- */
.quick-row {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 10px;
  margin-bottom: 14px;
}
.quick {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  padding: 12px 8px;
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  color: var(--text);
  transition: background 0.15s ease, border-color 0.15s ease, transform 0.08s ease;
}
.quick:hover { background: var(--card-hover); border-color: var(--accent); }
.quick:active { transform: scale(0.98); }
.quick-icon { font-size: 22px; line-height: 1; }
.quick-name { font-size: 12px; font-weight: 700; }
.quick-badge {
  font-size: 10px;
  font-weight: 800;
  color: var(--accent);
  background: var(--accent-soft);
  border-radius: var(--radius-pill);
  padding: 2px 8px;
}

.install-btn { margin-bottom: 22px; font-size: 15px; }

.journey-head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  margin-bottom: 16px;
}
.journey-head h3 { font-size: 18px; }
</style>
