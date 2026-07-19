<script setup>
import { onMounted, ref, computed } from 'vue'
import { BookOpen, Drama, Flame, Headphones, Layers, MessageCircle, Pencil, Smartphone, Snowflake, Sparkles, Wrench, Zap } from 'lucide-vue-next'
import LoylyIcon from '../components/icons/LoylyIcon.vue'
import { useAuthStore } from '../stores/auth'
import { usePwaInstall } from '../composables/usePwaInstall'
import { usePrefs } from '../composables/usePrefs'
import LessonPath from '../components/LessonPath.vue'
import { rankFor } from '../utils/ranks'
import api from '../api'

const auth = useAuthStore()
const { installPrompt, install } = usePwaInstall()
const { dailyGoal, prefs } = usePrefs()

// The greeting subtitle reflects the "why" picked at intake, so the goal a
// learner chose is echoed back every day - not buried on the Situations page.
const GOAL_SUBTITLES = {
  move: 'Finnish for your life in Finland',
  travel: 'Finnish for your visits to Finland',
  family: 'Finnish to connect with family & friends',
  casual: 'Exploring spoken Finnish, one sauna at a time'
}
const greetingSub = computed(() => GOAL_SUBTITLES[prefs.value.goal] ?? 'Learning everyday spoken Finnish')

const lessons = ref([])
const loading = ref(true)
// Today's woven plan, previewed so the four-skill session is visible from the
// dashboard - not a surprise you only meet after tapping Start. Same GET the
// session uses (idempotent, stable per day), fetched alongside everything else.
const plan = ref(null)

onMounted(async () => {
  try {
    const [, lessonsRes, planRes] = await Promise.all([
      auth.fetchUser(),
      api.get('/lessons'),
      api.get('/today-session', { params: { size: dailyGoal() } }).catch(() => null)
    ])
    lessons.value = lessonsRes.data.lessons
    plan.value = planRes?.data ?? null
  } finally {
    loading.value = false
  }
})

// The step lineup for today: the sentence block, then whatever the theme map
// wove in (a conversation to hear, a drill to bend), then the speaking beat.
// Empty on a caught-up day (no sentences) so the card simply doesn't show.
const planSteps = computed(() => {
  const p = plan.value
  if (!p?.sentences?.length) return []

  const steps = [{ icon: Layers, kind: 'Learn', title: `${p.sentences.length} sentence${p.sentences.length > 1 ? 's' : ''}` }]
  const w = p.woven ?? {}
  if (w.listening) steps.push({ icon: Headphones, kind: 'Listen', title: w.listening.title })
  if (w.transform) steps.push({ icon: Wrench, kind: 'Bend', title: w.transform.title })
  if (w.listening2) steps.push({ icon: Headphones, kind: 'Listen', title: w.listening2.title })
  steps.push({ icon: Sparkles, kind: 'Use', title: 'Say it for real' })
  return steps
})

// First-run explainer: a plain-language key to the vocabulary the rest of the
// dashboard assumes (streak, XP, levels, "due"). Shown until dismissed - hover
// tooltips are invisible on touch, and this is a mobile-first PWA.
const showIntro = ref(localStorage.getItem('ss_dash_intro_done') !== '1')
function dismissIntro() {
  showIntro.value = false
  localStorage.setItem('ss_dash_intro_done', '1')
}

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

// Feedback box: the private channel to the maker. Collapsed to one line
// until tapped; throttled server-side (5 per 10 min).
const fbOpen = ref(false)
const fbText = ref('')
const fbSending = ref(false)
const fbSent = ref(false)
const fbError = ref('')

async function sendFeedback() {
  if (fbSending.value || fbText.value.trim().length < 5) return
  fbSending.value = true
  fbError.value = ''
  try {
    await api.post('/feedback', { message: fbText.value.trim() })
    fbSent.value = true
    fbText.value = ''
  } catch (e) {
    fbError.value = e.response?.status === 429
      ? "That's plenty for now - try again in a few minutes."
      : "Couldn't send - check your connection and try again."
  } finally {
    fbSending.value = false
  }
}
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
        <div class="greet-block">
          <h2 class="greeting">Hei, {{ auth.user?.name }}!</h2>
          <p class="greeting-sub muted">{{ greetingSub }}</p>
        </div>
        <div class="chips">
          <span class="chip" title="XP — points earned from every completed exercise" aria-label="Experience points">
            <Zap class="chip-icon" aria-hidden="true" />{{ auth.user?.xp ?? 0 }}
            <span class="chip-label">XP</span>
          </span>
          <span class="chip" :title="streakTitle" aria-label="Day streak">
            <Flame class="chip-icon" aria-hidden="true" />{{ auth.user?.streak ?? 0 }}
            <span class="chip-label">day streak</span>
            <span v-if="auth.user?.streak_freezes" class="chip-freeze"><Snowflake class="freeze-ico" aria-hidden="true" />{{ auth.user.streak_freezes }}</span>
          </span>
        </div>
      </header>

      <!-- hero: the ONE action on this page. Land here, tap it, you're in the
           session. The session preview hangs off the button as a closed drawer -
           joined to it visually, so the button stays the focus but the details
           are one tap away. -->
      <div class="session-block">
        <router-link to="/session" class="btn btn-primary btn-block session-btn">
          <span class="session-label"><LoylyIcon class="session-ico" aria-hidden="true" /> Start Sauna Session</span>
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

        <!-- today's session preview: closed by default, joined to the button -->
        <details class="plan-fold">
          <summary class="plan-summary">
            <span class="plan-summary-label">Today's session</span>
            <span class="plan-summary-meta">
              <span class="plan-summary-hint">{{ planSteps.length ? `${planSteps.length} steps` : 'All caught up' }}</span>
              <svg class="fold-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6" /></svg>
            </span>
          </summary>
          <div class="plan-body">
            <ol v-if="planSteps.length" class="plan-steps">
              <li v-for="(s, i) in planSteps" :key="i" class="plan-step">
                <span class="plan-ico"><component :is="s.icon" aria-hidden="true" /></span>
                <span class="plan-text"><b class="plan-kind">{{ s.kind }}</b><span class="plan-name">{{ s.title }}</span></span>
              </li>
            </ol>
            <p v-else class="plan-empty muted">You're all caught up — nothing due right now. Tap Start for an extra round any time.</p>
            <p class="plan-foot">
              <template v-if="nextLesson">Next: <b>{{ nextLesson.title }}</b><span class="foot-dot">·</span></template>
              <template v-if="dueCount">{{ dueCount }} ready to review<span class="foot-dot">·</span></template>
              <span :class="{ 'foot-met': goalMet }">{{ todayDone }}/{{ goal }} done today{{ goalMet ? ' ✓' : '' }}</span>
            </p>
          </div>
        </details>
      </div>

      <!-- first-run key to the app, below the hero so the action comes first and
           the explainer is there only if a newcomer wants it -->
      <div v-if="showIntro" class="card intro-card">
        <button class="intro-x" aria-label="Dismiss" @click="dismissIntro">×</button>
        <p class="intro-title">👋 New here? Here's the whole app in 20 seconds</p>
        <ul class="intro-list">
          <li><b>One Sauna Session a day.</b> Each one mixes a few sentences with a real conversation to hear, a grammar drill, and a bit of speaking.</li>
          <li><b>Sentences come back over time.</b> "Ready to review" means one is due again — seeing it right before you'd forget is what makes it stick.</li>
          <li><b>Streak &amp; XP</b> reward showing up daily. <b>Levels A0 → B2</b> mark how far you've come — A0 is your first words, B2 is nearly fluent.</li>
        </ul>
        <button class="btn btn-primary intro-go" @click="dismissIntro">Got it — let's go</button>
      </div>

      <!-- a recently broken streak can be relit for XP -->
      <div v-if="auth.user?.streak_repairable" class="card repair-card">
        <div class="repair-info">
          <p class="repair-title"><Snowflake class="repair-ico" aria-hidden="true" /> Your {{ auth.user.broken_streak }}-day streak went cold</p>
          <p class="repair-sub muted">Relight it within 3 days and the chain continues like you never missed.</p>
          <p v-if="repairError" class="repair-error">{{ repairError }}</p>
        </div>
        <button
          class="btn btn-primary repair-btn"
          :disabled="repairing || (auth.user?.xp ?? 0) < REPAIR_COST"
          @click="repairStreak"
        >
          <template v-if="(auth.user?.xp ?? 0) < REPAIR_COST">Need {{ REPAIR_COST }} XP</template>
          <template v-else><Flame class="relight-ico" aria-hidden="true" /> Relight — {{ REPAIR_COST }} XP</template>
        </button>
      </div>

      <!-- progress details, folded away by default so the landing view stays
           calm - the essentials (XP, streak) already live in the header chips.
           Native <details>, so it's open/close with no extra state. -->
      <details v-if="showWeek || (auth.user?.xp ?? 0) > 0" class="progress-fold">
        <summary class="progress-summary">
          <span>Your progress</span>
          <svg class="fold-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6" /></svg>
        </summary>
        <div class="progress-body">
          <!-- rank: one slim strip instead of a full card -->
          <div class="card rank-strip" :title="`${rank.title} — sauna rank grows with XP`">
            <component :is="rank.icon" class="rank-icon" aria-hidden="true" />
            <div class="rank-mid">
              <div class="rank-row">
                <span class="rank-title">{{ rank.title }}</span>
                <span class="rank-next muted">
                  <template v-if="rank.next">{{ rank.next.xp - (auth.user?.xp ?? 0) }} XP to {{ rank.next.title }}</template>
                  <template v-else>legenda!</template>
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
        </div>
      </details>

      <!-- the path: the curriculum map, above the optional extras so a newcomer
           sees what they're learning and how far it goes before the buffet. -->
      <div class="journey-head">
        <h3>Your path</h3>
        <span class="muted">{{ auth.stats?.mastered_count ?? 0 }} / {{ auth.stats?.total_sentences ?? 0 }} sentences mastered</span>
      </div>
      <LessonPath :lessons="lessons" />

      <!-- optional extra practice: the same skills that are already in the daily
           session, available on their own. English descriptors sit under the
           Finnish names so a newcomer isn't left guessing what "Taivutus" is. -->
      <div class="quick-head">
        <h3>More practice</h3>
        <span class="muted">Optional — each skill on its own, any time</span>
      </div>
      <div class="quick-row">
        <router-link to="/listening" class="quick">
          <Headphones class="quick-icon" aria-hidden="true" />
          <span class="quick-name">Kuuntelu</span>
          <span class="quick-sub">Listen</span>
        </router-link>
        <router-link to="/transforms" class="quick">
          <Wrench class="quick-icon" aria-hidden="true" />
          <span class="quick-name">Taivutus</span>
          <span class="quick-sub">Endings</span>
        </router-link>
        <router-link to="/chat" class="quick">
          <MessageCircle class="quick-icon" aria-hidden="true" />
          <span class="quick-name">Sauna Chat</span>
          <span class="quick-sub">Talk</span>
        </router-link>
        <router-link to="/scenarios" class="quick">
          <Drama class="quick-icon" aria-hidden="true" />
          <span class="quick-name">Situations</span>
          <span class="quick-sub">Roleplay</span>
        </router-link>
        <router-link to="/words" class="quick">
          <BookOpen class="quick-icon" aria-hidden="true" />
          <span class="quick-name">Word bank</span>
          <span class="quick-sub">Your words</span>
          <span v-if="auth.stats?.words_due" class="quick-badge">{{ auth.stats.words_due }} ready</span>
        </router-link>
        <!-- appears only once chat corrections have piled up into review cards -->
        <router-link v-if="auth.stats?.mistakes_due" to="/mistakes/review" class="quick">
          <Pencil class="quick-icon" aria-hidden="true" />
          <span class="quick-name">Chat mistakes</span>
          <span class="quick-sub">Fixes</span>
          <span class="quick-badge">{{ auth.stats.mistakes_due }} ready</span>
        </router-link>
      </div>

      <button v-if="installPrompt" class="btn btn-ghost btn-block install-btn" @click="install">
        <Smartphone class="install-ico" aria-hidden="true" /> Install SaunaSpeak on this device
      </button>

      <!-- feedback: complaints belong here, where they get fixed -->
      <div class="card feedback">
        <button v-if="!fbOpen && !fbSent" class="fb-toggle" @click="fbOpen = true">
          <MessageCircle class="fb-ico" aria-hidden="true" /> Something confusing, broken, or missing? Tell me
        </button>
        <template v-else-if="!fbSent">
          <p class="fb-title"><MessageCircle class="fb-ico" aria-hidden="true" /> Feedback</p>
          <p class="fb-sub muted">Goes straight to the maker. Rough honesty welcome.</p>
          <textarea
            v-model="fbText"
            class="fb-input"
            rows="3"
            maxlength="2000"
            placeholder="What should be better?"
          ></textarea>
          <p v-if="fbError" class="fb-error">{{ fbError }}</p>
          <div class="fb-actions">
            <button class="btn btn-ghost" @click="fbOpen = false">Cancel</button>
            <button class="btn btn-primary" :disabled="fbSending || fbText.trim().length < 5" @click="sendFeedback">
              {{ fbSending ? 'Sending…' : 'Send' }}
            </button>
          </div>
        </template>
        <p v-else class="fb-thanks">Kiitos! Read by a human, promise.</p>
      </div>
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
.chip-icon { width: 15px; height: 15px; color: var(--accent); flex-shrink: 0; }
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
  display: inline-flex;
  align-items: center;
  gap: 2px;
}
.freeze-ico { width: 11px; height: 11px; }

/* ---- hero ---- */
.session-block { margin-bottom: 14px; }
.session-btn {
  position: relative;
  font-size: 18px;
  padding: 17px 52px 17px 17px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 0;
  /* square off the bottom so the session drawer joins seamlessly below */
  border-bottom-left-radius: 0;
  border-bottom-right-radius: 0;
}
.session-label { display: inline-flex; align-items: center; gap: 8px; }
.session-ico { width: 19px; height: 19px; flex-shrink: 0; }
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
.greet-block { min-width: 0; }
.greeting-sub { font-size: 12.5px; margin-top: 1px; }

/* ---- first-run intro: plain-language key, dismissible ---- */
.intro-card { position: relative; padding: 16px 16px 14px; margin-bottom: 14px; border-color: var(--accent); }
.intro-x {
  position: absolute;
  top: 8px;
  right: 10px;
  background: none;
  border: none;
  color: var(--text-dim);
  font-size: 22px;
  line-height: 1;
  cursor: pointer;
  padding: 2px 6px;
}
.intro-x:hover { color: var(--text); }
.intro-title { font-weight: 800; font-size: 15px; margin-bottom: 10px; padding-right: 24px; }
.intro-list { display: flex; flex-direction: column; gap: 8px; margin: 0 0 14px; padding-left: 18px; }
.intro-list li { font-size: 13.5px; line-height: 1.5; color: var(--text-dim); }
.intro-list b { color: var(--text); }
.intro-go { font-size: 14px; }

/* ---- today's session: a closed drawer joined to the hero button ---- */
.plan-fold {
  /* borders on three sides only - the top edge is the button, so they read as
     one connected unit. Square top corners, rounded bottom to close the shape. */
  border: 1px solid var(--border);
  border-top: none;
  border-radius: 0 0 var(--radius) var(--radius);
  background: var(--card);
}
.plan-summary {
  list-style: none;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 11px 14px;
  border-radius: 0 0 var(--radius) var(--radius);
  transition: background 0.15s ease;
}
.plan-summary::-webkit-details-marker { display: none; }
.plan-summary:hover { background: var(--card-hover); }
.plan-fold[open] .plan-summary { border-radius: 0; }
.plan-summary-label {
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: var(--text-dim);
}
.plan-summary-meta { display: inline-flex; align-items: center; gap: 8px; min-width: 0; }
.plan-summary-hint { font-size: 12.5px; font-weight: 700; color: var(--text-dim); }
.plan-fold[open] .fold-chevron { transform: rotate(180deg); }
.plan-body { padding: 12px 14px; border-top: 1px solid var(--border); }
.plan-steps { display: flex; flex-direction: column; gap: 2px; list-style: none; }
.plan-step { display: flex; align-items: center; gap: 11px; padding: 6px 0; position: relative; }
/* a thin connector so it reads as a sequence, not a list */
.plan-step:not(:last-child)::after {
  content: '';
  position: absolute;
  left: 13px;
  top: 32px;
  bottom: -2px;
  width: 2px;
  background: var(--border);
}
.plan-ico {
  width: 28px;
  height: 28px;
  flex-shrink: 0;
  display: grid;
  place-items: center;
  border-radius: 50%;
  background: var(--accent-soft);
  color: var(--accent);
  z-index: 1;
}
.plan-ico svg { width: 15px; height: 15px; }
.plan-text { display: flex; flex-direction: column; gap: 1px; min-width: 0; }
.plan-kind { font-size: 10px; font-weight: 800; letter-spacing: 0.05em; text-transform: uppercase; color: var(--text-dim); }
.plan-name { font-size: 14px; font-weight: 700; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.plan-empty { font-size: 13px; line-height: 1.5; padding: 2px 0 4px; }
.plan-foot {
  margin-top: 10px;
  padding-top: 10px;
  border-top: 1px solid var(--border);
  font-size: 12.5px;
  color: var(--text-dim);
  line-height: 1.6;
}
.plan-foot b { color: var(--text); font-weight: 700; }
.foot-dot { margin: 0 7px; opacity: 0.5; }
.foot-met { color: var(--green); font-weight: 700; }

.repair-card {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 14px;
  border-color: var(--accent-2);
}
.repair-title { font-weight: 800; font-size: 15px; display: flex; align-items: center; gap: 6px; }
.repair-ico { width: 15px; height: 15px; color: var(--text-dim); flex-shrink: 0; }
.relight-ico { width: 14px; height: 14px; vertical-align: -2px; margin-right: 2px; }
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
.rank-icon { width: 24px; height: 24px; flex-shrink: 0; color: var(--accent); }
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

/* ---- progress fold: secondary stats, tucked away so the default view is calm ---- */
.progress-fold { margin-bottom: 14px; }
.progress-summary {
  list-style: none;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 11px 14px;
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  font-weight: 800;
  font-size: 14px;
  color: var(--text);
  transition: border-color 0.15s ease;
}
.progress-summary::-webkit-details-marker { display: none; }
.progress-summary:hover { border-color: var(--accent); }
.progress-fold[open] .progress-summary { border-color: var(--accent); margin-bottom: 14px; }
.fold-chevron { width: 16px; height: 16px; color: var(--text-dim); flex-shrink: 0; transition: transform 0.2s ease; }
.progress-fold[open] .fold-chevron { transform: rotate(180deg); }
.progress-body > .card:last-child { margin-bottom: 0; }

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
.quick-head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 10px;
  margin-bottom: 10px;
}
.quick-head h3 { font-size: 16px; }
.quick-head .muted { font-size: 12px; text-align: right; }
.quick-row {
  display: grid;
  /* Wrapping grid: tiles hold a comfortable min width and flow onto a second
     row rather than being crushed into one line. auto-fill keeps every tile
     the same width regardless of how many render (the mistakes tile is
     conditional) - on a phone that's 3 per row, on desktop all in one. */
  grid-template-columns: repeat(auto-fill, minmax(96px, 1fr));
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
.quick-icon { width: 22px; height: 22px; color: var(--accent); }
.quick-name { font-size: 12px; font-weight: 700; text-align: center; line-height: 1.15; }
.quick-sub {
  font-size: 9.5px;
  font-weight: 700;
  color: var(--text-dim);
  text-transform: uppercase;
  letter-spacing: 0.03em;
  text-align: center;
  line-height: 1.1;
}
.quick-badge {
  font-size: 10px;
  font-weight: 800;
  color: var(--accent);
  background: var(--accent-soft);
  border-radius: var(--radius-pill);
  padding: 2px 8px;
}

.install-btn { margin-bottom: 22px; font-size: 15px; display: flex; align-items: center; justify-content: center; gap: 7px; }
.install-ico { width: 16px; height: 16px; flex-shrink: 0; }

/* ---- feedback box ---- */
.feedback { margin-top: 22px; padding: 14px 16px; display: flex; flex-direction: column; gap: 10px; }
.fb-toggle {
  background: none;
  border: none;
  color: var(--text-dim);
  font-family: inherit;
  font-size: 13.5px;
  font-weight: 700;
  cursor: pointer;
  padding: 2px 0;
  text-align: center;
}
.fb-toggle:hover { color: var(--accent); }
.fb-title { font-size: 15px; font-weight: 800; display: flex; align-items: center; gap: 6px; }
.fb-ico { width: 14px; height: 14px; vertical-align: -2px; flex-shrink: 0; }
.fb-sub { font-size: 12.5px; margin-top: -6px; }
.fb-input {
  background: var(--bg-soft);
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  color: var(--text);
  font-family: inherit;
  font-size: 14px;
  padding: 10px 12px;
  resize: vertical;
  outline: none;
}
.fb-input:focus { border-color: var(--accent); }
.fb-error { font-size: 12.5px; color: var(--red); }
.fb-actions { display: flex; justify-content: flex-end; gap: 8px; }
.fb-thanks { text-align: center; font-size: 14px; font-weight: 700; padding: 4px 0; }

.journey-head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  margin-bottom: 16px;
}
.journey-head h3 { font-size: 18px; }

/* ---- small phones ---- */
@media (max-width: 380px) {
  /* greeting + chips: let the chips wrap under the greeting cleanly and keep
     the heading from crowding them off the edge */
  .greeting { font-size: 21px; }
  .chips { flex-wrap: wrap; }

  /* hero button: trim so the label + goal ring never collide on a narrow row */
  .session-btn { font-size: 16.5px; padding: 16px 46px 16px 14px; }

  /* the two 7-across strips are the tightest thing on the page - shrink the
     gap so the day cells keep a usable width down to ~320px */
  .week-days,
  .schedule-days { gap: 5px; }
  .wday-dot { width: 24px; height: 24px; }

  /* section headings + their trailing note can collide when both are long;
     let them stack instead of squeezing onto one baseline */
  .quick-head,
  .journey-head { flex-wrap: wrap; gap: 2px 10px; }
  .quick-head .muted,
  .journey-head .muted { text-align: left; }
}
</style>
