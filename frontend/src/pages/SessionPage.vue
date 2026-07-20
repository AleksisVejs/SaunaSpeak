<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { BookOpen, Check, Flame, MessageCircle, PartyPopper, RotateCcw, Snowflake, Volume2, X, Zap } from 'lucide-vue-next'
import LoylyIcon from '../components/icons/LoylyIcon.vue'
import { useSessionStore } from '../stores/session'
import { useAuthStore } from '../stores/auth'
import SentenceCard from '../components/SentenceCard.vue'
import PracticeInput from '../components/PracticeInput.vue'
import ListeningStep from '../components/ListeningStep.vue'
import TransformStep from '../components/TransformStep.vue'
import UseStep from '../components/UseStep.vue'
import { cardKind, clozeWord } from '../utils/practice'
import { rankFor } from '../utils/ranks'
import { useFinnishAudio } from '../composables/useFinnishAudio'
import { usePrefs } from '../composables/usePrefs'

const session = useSessionStore()
const auth = useAuthStore()
const { playSentence } = useFinnishAudio()

const card = ref(null)
const revealed = ref(false)
const submitting = ref(false)
const error = ref('')
// The self-grade the check result points at: correct → Good, wrong → Again.
// One press of Enter (or the highlighted button) applies it; Again/Easy stay
// one tap away as the manual override.
const suggested = ref(null)

// Load in setup (not onMounted) so the reset state is in place before the first render.
session.loadToday()

watch(
  () => session.index,
  () => {
    revealed.value = false
    suggested.value = null
  }
)

// The current step's sentence, or null when we're on a woven step
// (listening / bend / use). The sentence-card computeds all key off this.
const sentenceStep = computed(() => (session.current?.type === 'sentence' ? session.current.sentence : null))

// study | cloze | dictation | recall - the exercise gets harder as the SRS stage rises.
const kind = computed(() => cardKind(sentenceStep.value?.status))

// Every card - including a new sentence's guess-first step - must be
// attempted (or given up on) before self-grading. Cards emit 'revealed'.
const canGrade = computed(() => revealed.value)

// Cloze checks just the missing word; the other kinds check the whole sentence.
const practiceExpected = computed(() => {
  const s = sentenceStep.value
  if (!s) return ''
  return kind.value === 'cloze' ? clozeWord(s.finnish_text) : s.finnish_text
})

// Cloze expects a single word, so the full-sentence translation would mislead
// the AI corrector there; the other kinds get the English meaning as an anchor.
const practiceTranslation = computed(() =>
  kind.value === 'cloze' ? '' : sentenceStep.value?.english_text || ''
)

// The kirjakieli form also counts as correct (speech recognition normalizes
// puhekieli to written Finnish). Whole-sentence kinds only - there's no
// word-level written mapping for a cloze gap.
const practiceWritten = computed(() =>
  kind.value === 'cloze' ? '' : sentenceStep.value?.written_text || ''
)

const practiceHints = {
  study: 'Your guess - say or type it in Finnish',
  cloze: 'The missing word',
  dictation: 'What did you hear?',
  recall: 'Say or type it in Finnish'
}

function onChecked(correct) {
  // Checking an attempt shows the answer, so reveal the card too - and the
  // result picks the suggested grade (override stays one tap away).
  suggested.value = correct ? 'good' : 'again'
  card.value?.reveal()
}

// Enter from the practice input (on an already-checked attempt) or anywhere
// else on the page applies the suggested grade.
function confirmSuggested() {
  if (canGrade.value && suggested.value) grade(suggested.value)
}

function onKey(e) {
  // The input's own Enter emits 'confirm'; this catches Enter after mic use
  // or button clicks, when focus is not in the input.
  if (e.key === 'Enter' && e.target.tagName !== 'INPUT' && e.target.tagName !== 'BUTTON') {
    confirmSuggested()
  }
}
onMounted(() => window.addEventListener('keydown', onKey))
onUnmounted(() => window.removeEventListener('keydown', onKey))

async function grade(g) {
  if (submitting.value) return
  submitting.value = true
  error.value = ''
  try {
    await session.completeSentence(g)
    if (session.finished) {
      await auth.fetchUser()
      startCelebration()
    }
  } catch {
    if (session.finished) {
      // Session saved but the user refetch failed - show final numbers unanimated.
      shownXp.value = totalXp.value
      rankPct.value = rank.value.pct
    } else {
      error.value = 'Something went wrong saving your progress. Try again.'
    }
  } finally {
    submitting.value = false
  }
}

// A woven step (listening / bend / use) reports done, with any XP its own
// endpoint already awarded. Advancing past the last step finishes the session.
async function onWovenDone(xp = 0) {
  if (submitting.value) return
  submitting.value = true
  error.value = ''
  try {
    await session.completeWoven(xp)
    if (session.finished) {
      await auth.fetchUser()
      startCelebration()
    }
  } catch {
    error.value = 'Something went wrong saving your progress. Try again.'
  } finally {
    submitting.value = false
  }
}

// --- end-of-session celebration: count the XP up, sweep the rank bar ---
const totalXp = computed(() => session.xpEarned + session.wovenXp + session.bonusXp)
const shownXp = ref(0)
const rankPct = ref(0)
// Transition stays off until the start position has actually painted -
// otherwise the bar animates 0 → start → target and visibly lurches.
const sweepOn = ref(false)

const rank = computed(() => rankFor(auth.user?.xp ?? 0))
// Where the learner stood before this session's XP landed.
const prevRank = computed(() => rankFor(Math.max(0, (auth.user?.xp ?? 0) - totalXp.value)))
const rankedUp = computed(() => rank.value.index > prevRank.value.index)

// A freeze lands on every completed streak week (see SessionController).
const freezeEarned = computed(() => {
  const u = auth.user
  return session.bonusXp > 0 && u?.streak > 0 && u.streak % 7 === 0 && u.streak_freezes > 0
})

function startCelebration() {
  const total = totalXp.value
  const start = performance.now()
  const step = (t) => {
    const p = Math.min(1, (t - start) / 900)
    shownXp.value = Math.round(total * (1 - (1 - p) ** 3))
    if (p < 1) requestAnimationFrame(step)
  }
  requestAnimationFrame(step)

  // Sweep the bar from the pre-session position (from 0 after a rank-up,
  // so the fill visibly enters the new rank). Double rAF guarantees the
  // start width is painted before the transition switches on.
  sweepOn.value = false
  rankPct.value = rankedUp.value ? 0 : prevRank.value.pct
  requestAnimationFrame(() =>
    requestAnimationFrame(() => {
      sweepOn.value = true
      rankPct.value = rank.value.pct
    })
  )
}

// --- tomorrow, made concrete ---
// The finish screen used to end on a wish ("see you tomorrow!"). Wishes
// don't retain; appointments do. Two mechanisms below:
// 1. The forecast: how many of today's sentences come due tomorrow - loss
//    aversion working for us instead of silently against the learner.
// 2. An implementation intention: learners without a chosen practice time
//    pick one here, once - it also times their reminder email.
const { prefs, savePrefs } = usePrefs()

const dueTomorrow = computed(() => {
  const d = new Date(Date.now() + 86400000)
  const key = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
  return auth.stats?.forecast?.find((r) => r.date === key)?.count ?? 0
})

const TIME_SLOTS = [
  { value: 'morning', label: '☀️ Morning' },
  { value: 'lunch', label: '☕ Midday' },
  { value: 'evening', label: '🌙 Evening' }
]
const pickedTime = ref(null)
const showTimePicker = computed(() => !prefs.value.practice_time && !pickedTime.value)

function pickTime(slot) {
  pickedTime.value = slot
  savePrefs({ practice_time: slot })
}

const slotLabel = computed(() => {
  const v = pickedTime.value ?? prefs.value.practice_time
  return { morning: 'in the morning', lunch: 'at midday', evening: 'in the evening' }[v] ?? 'tomorrow'
})

// The trial pitch at the happiest moment of the day: right after a finished
// session, when the mistakes are fresh and "AI feedback on every attempt"
// means something concrete. Shows for free accounts until acted on or
// dismissed - never again after either.
const UPSELL_STORE = 'ss_upsell_session_done'
const upsellDismissed = ref(localStorage.getItem(UPSELL_STORE) === '1')
const showUpsell = computed(() => auth.user?.is_premium === false && !upsellDismissed.value)

function upsellActed(source) {
  window.umami?.track('upsell_click', { source })
  dismissUpsell()
}
function dismissUpsell() {
  upsellDismissed.value = true
  try {
    localStorage.setItem(UPSELL_STORE, '1')
  } catch {
    // storage blocked - it'll simply show again next session
  }
}

// End-of-session consolidation: everything studied today, once more with audio.
const studied = computed(() => {
  const unique = new Map()
  session.sentences.forEach((s) => unique.set(s.id, s))
  return [...unique.values()]
})

const CONFETTI_COLORS = ['#f59e0b', '#fb923c', '#34d399', '#eef0f4', '#f87171']

function confettiStyle(i) {
  return {
    left: `${(i * 41) % 100}%`,
    background: CONFETTI_COLORS[i % CONFETTI_COLORS.length],
    animationDelay: `${(i % 8) * 0.12}s`,
    animationDuration: `${2 + ((i * 7) % 10) / 6}s`
  }
}
</script>

<template>
  <div class="session-page">
  <div v-if="session.loading" class="spinner"></div>

  <!-- Finished screen -->
  <div v-else-if="session.finished" class="finish card">
    <div class="confetti" aria-hidden="true">
      <span v-for="i in 24" :key="i" class="confetti-piece" :style="confettiStyle(i)"></span>
    </div>
    <img class="finish-icon vaino" src="/vaino-loyly.png" alt="Väinö throwing water on the sauna stones" />
    <h2>Session complete!</h2>
    <p class="muted">Löyly earned. See you tomorrow!</p>
    <div class="xp-summary">
      <div class="xp-line"><span>Sentences</span><b>+{{ session.xpEarned }} XP</b></div>
      <div v-if="session.wovenXp" class="xp-line"><span>Listening &amp; drills</span><b>+{{ session.wovenXp }} XP</b></div>
      <div class="xp-line"><span>Daily bonus</span><b>+{{ session.bonusXp }} XP</b></div>
      <div class="xp-line total"><span>Total</span><b>+{{ shownXp }} XP</b></div>
    </div>

    <div class="rank-progress">
      <p v-if="rankedUp" class="rankup"><PartyPopper class="note-ico" aria-hidden="true" /> Rank up: <component :is="rank.icon" class="rank-ico" aria-hidden="true" /> <b>{{ rank.title }}</b></p>
      <p v-else class="rank-line">
        <span class="rank-name"><component :is="rank.icon" class="rank-ico" aria-hidden="true" /> {{ rank.title }}</span>
        <span v-if="rank.next" class="muted rank-to-next">{{ rank.next.xp - (auth.user?.xp ?? 0) }} XP to {{ rank.next.title }}</span>
      </p>
      <div class="progress-track rank-track"><div class="progress-fill rank-sweep" :class="{ sweeping: sweepOn }" :style="{ width: rankPct + '%' }"></div></div>
    </div>

    <div class="streak-note"><Flame class="note-ico" aria-hidden="true" /> {{ auth.user?.streak }} day streak</div>
    <p v-if="freezeEarned" class="freeze-note"><Snowflake class="note-ico" aria-hidden="true" /> Week complete — you earned a streak freeze! It auto-saves one missed day.</p>
    <p v-else-if="auth.user?.streak_freezes" class="freeze-note muted"><Snowflake class="note-ico" aria-hidden="true" /> {{ auth.user.streak_freezes }} freeze{{ auth.user.streak_freezes > 1 ? 's' : '' }} banked — each auto-saves one missed day</p>

    <!-- Tomorrow, made concrete: the appointment replaces the wish -->
    <div class="tomorrow card-inset">
      <p v-if="dueTomorrow" class="tomorrow-line">
        <b>{{ dueTomorrow }} sentence{{ dueTomorrow > 1 ? 's' : '' }} come{{ dueTomorrow > 1 ? '' : 's' }} back tomorrow.</b>
        Review them then and they stick for weeks — skip it and they fade.
      </p>
      <p v-else class="tomorrow-line"><b>Come back tomorrow</b> — reviewing right on schedule is what moves sentences into long-term memory.</p>

      <template v-if="showTimePicker">
        <p class="tomorrow-q">When will you practice tomorrow?</p>
        <div class="slot-row">
          <button v-for="s in TIME_SLOTS" :key="s.value" class="slot-chip" @click="pickTime(s.value)">{{ s.label }}</button>
        </div>
      </template>
      <p v-else class="tomorrow-set muted">Väinö will save your seat {{ slotLabel }}. 🧖</p>
    </div>

    <!-- Löyly+ at the moment it makes sense: the session just surfaced real
         mistakes, and the paid tier is the thing that talks them through. -->
    <div v-if="showUpsell" class="upsell card">
      <button class="upsell-x" aria-label="Not now" @click="dismissUpsell">×</button>
      <p class="upsell-head"><LoylyIcon class="upsell-ico" aria-hidden="true" /> Löyly+ · 3 days free</p>
      <p class="upsell-text muted">
        Nice work today. Want to actually <b>use</b> those sentences? Väinö chats
        with you in spoken Finnish, fixes your mistakes and explains why.
      </p>
      <router-link to="/upgrade" class="btn btn-primary btn-block" @click="upsellActed('post_session')">
        Start your 3 free days
      </router-link>
      <router-link to="/chat" class="upsell-try muted" @click="upsellActed('post_session_chat')">
        <MessageCircle class="upsell-try-ico" aria-hidden="true" /> or try a free chat with Väinö first ›
      </router-link>
    </div>

    <div v-if="studied.length" class="recap">
      <p class="recap-title"><BookOpen class="note-ico" aria-hidden="true" /> Quick recap - say each one out loud one more time</p>
      <div v-for="s in studied" :key="s.id" class="recap-row">
        <button class="recap-play" :title="'Play ' + s.finnish_text" @click="playSentence(s.finnish_text, s.audio_url)"><Volume2 class="play-ico" aria-hidden="true" /></button>
        <div class="recap-texts">
          <p class="recap-fi">{{ s.finnish_text }}</p>
          <p class="recap-en muted">{{ s.english_text }}</p>
        </div>
      </div>
    </div>

    <!-- Momentum is precious: offer another round before the exit. If
         nothing is left, the reload lands on the "all caught up" state. -->
    <button class="btn btn-primary btn-block more-btn" @click="session.loadToday()"><Flame class="note-ico" aria-hidden="true" /> Keep going - another round</button>
    <router-link to="/dashboard" class="btn btn-ghost btn-block">Back to dashboard</router-link>
  </div>

  <!-- Empty state -->
  <div v-else-if="!session.total" class="finish card">
    <img class="finish-icon vaino" src="/vaino-relax.png" alt="Väinö relaxing on the sauna bench" />
    <h2>All caught up!</h2>
    <p class="muted">No sentences due right now. Come back later for more löyly.</p>
    <router-link to="/dashboard" class="btn btn-ghost btn-block">Back to dashboard</router-link>
  </div>

  <!-- Active session -->
  <div v-else class="session">
    <div class="session-top">
      <router-link to="/dashboard" class="quit" aria-label="End session"><X class="quit-ico" aria-hidden="true" /></router-link>
      <div class="progress-track session-progress">
        <div class="progress-fill" :style="{ width: session.progressPct + '%' }"></div>
      </div>
      <span class="counter">{{ session.index + 1 }}/{{ session.total }}</span>
    </div>

    <transition name="slide-fade" mode="out-in">
      <!-- Sentence card: recall / cloze / dictation, self-graded (unchanged). -->
      <div v-if="session.current.type === 'sentence'" :key="`s-${session.index}`" class="step-wrap">
        <SentenceCard
          ref="card"
          :sentence="session.current.sentence"
          :status="session.current.sentence.status"
          mode="study"
          @revealed="revealed = true"
        />

        <PracticeInput
          :key="`practice-${session.index}`"
          :expected="practiceExpected"
          :translation="practiceTranslation"
          :written="practiceWritten"
          :placeholder="practiceHints[kind]"
          @checked="onChecked"
          @confirm="confirmSuggested"
        />

        <div class="grade-zone">
          <p v-if="!canGrade" class="muted grade-hint">Try it from memory, then check - or reveal the answer to grade yourself.</p>
          <!-- The check result pre-picks a grade (correct → Good, miss → Again):
               Enter or the highlighted button confirms it, the others override. -->
          <div v-else class="grade-row">
            <button
              class="grade-btn again"
              :class="suggested === 'again' ? 'btn btn-primary suggested-again' : 'btn btn-ghost'"
              :disabled="submitting"
              @click="grade('again')"
            >
              <RotateCcw class="grade-ico" aria-hidden="true" /> Again<kbd v-if="suggested === 'again'" class="key-hint">↵</kbd>
            </button>
            <button
              class="grade-btn"
              :class="suggested !== 'again' ? 'btn btn-primary' : 'btn btn-ghost'"
              :disabled="submitting"
              @click="grade('good')"
            >
              <Check class="grade-ico" aria-hidden="true" /> Good<kbd v-if="suggested === 'good'" class="key-hint">↵</kbd>
            </button>
            <button class="btn btn-ghost grade-btn easy" :disabled="submitting" @click="grade('easy')">
              <Zap class="grade-ico" aria-hidden="true" /> Easy
            </button>
          </div>
        </div>
      </div>

      <!-- Woven steps: hear it, bend it, use it - the four-skill tail. -->
      <ListeningStep v-else-if="session.current.type === 'listening'" :key="`l-${session.index}`" :data="session.current.data" @done="onWovenDone" />
      <TransformStep v-else-if="session.current.type === 'transform'" :key="`t-${session.index}`" :data="session.current.data" @done="onWovenDone" />
      <UseStep v-else-if="session.current.type === 'use'" :key="`u-${session.index}`" :data="session.current.data" @done="onWovenDone" />
    </transition>

    <!-- one error slot for the whole flow: sentence grades AND woven steps land
         here, so a failed save is never silent on a listen/bend/use step. -->
    <div v-if="error" class="error-msg session-error" role="alert">{{ error }}</div>
  </div>
  </div>
</template>

<style scoped>
/* single root element - required by the page transition in App.vue */
.session-page { display: flex; flex-direction: column; flex: 1; }
.session { display: flex; flex-direction: column; gap: 18px; flex: 1; }
/* the swappable step fills the same column the sentence card used to */
.step-wrap { display: flex; flex-direction: column; gap: 18px; flex: 1; }
.session-top { display: flex; align-items: center; gap: 14px; }
.quit { color: var(--text-dim); font-size: 18px; padding: 4px; }
.quit:hover { color: var(--text); }
.session-progress { flex: 1; }
.counter { font-size: 13px; font-weight: 700; color: var(--text-dim); }

.grade-zone { margin-top: auto; }
.grade-hint { text-align: center; padding: 14px 0; }
.grade-row { display: grid; grid-template-columns: 1fr 1.3fr 1fr; gap: 10px; }
.grade-btn { padding: 15px 8px; font-size: 15px; display: inline-flex; align-items: center; justify-content: center; gap: 6px; }
.grade-ico { width: 15px; height: 15px; flex-shrink: 0; }
.note-ico { width: 14px; height: 14px; vertical-align: -2px; flex-shrink: 0; }
.play-ico { width: 16px; height: 16px; display: block; }
.more-btn { display: flex; align-items: center; justify-content: center; gap: 7px; }
.quit-ico { width: 18px; height: 18px; display: block; }
.rank-ico { width: 15px; height: 15px; vertical-align: -2px; color: var(--accent); }
.rank-name { display: inline-flex; align-items: center; gap: 5px; }
.grade-btn.again:hover:not(:disabled) { border-color: var(--red); color: var(--red); }
.grade-btn.easy:hover:not(:disabled) { border-color: var(--green); color: var(--green); }
/* A missed attempt suggests Again - tint the primary treatment toward red so
   the confirm action reads as "yes, that was a lapse". */
.grade-btn.suggested-again { background: var(--red); border-color: var(--red); }
.key-hint {
  font-family: inherit;
  font-size: 10.5px;
  font-weight: 700;
  border: 1px solid currentColor;
  border-radius: 4px;
  padding: 0 4px;
  margin-left: 6px;
  opacity: 0.75;
}

/* card swap */
.slide-fade-enter-active, .slide-fade-leave-active { transition: opacity 0.22s ease, transform 0.22s ease; }
.slide-fade-enter-from { opacity: 0; transform: translateX(28px); }
.slide-fade-leave-to { opacity: 0; transform: translateX(-28px); }

.finish { text-align: center; margin-top: 6vh; display: flex; flex-direction: column; gap: 14px; position: relative; overflow: hidden; }
.finish-icon { font-size: 52px; animation: pop-in 0.45s cubic-bezier(0.34, 1.56, 0.64, 1); }
.finish-icon.vaino { width: 132px; height: 132px; margin: 0 auto; }
@keyframes pop-in {
  from { transform: scale(0.3); opacity: 0; }
  to { transform: scale(1); opacity: 1; }
}
.xp-summary { background: var(--bg-soft); border-radius: var(--radius-sm); padding: 14px 18px; }
.xp-line { display: flex; justify-content: space-between; padding: 6px 0; color: var(--text-dim); }
.xp-line b { color: var(--accent); }
.xp-line.total { border-top: 1px solid var(--border); margin-top: 4px; padding-top: 10px; color: var(--text); }
.rank-progress { display: flex; flex-direction: column; gap: 8px; text-align: left; }
.rank-line { display: flex; justify-content: space-between; align-items: baseline; font-weight: 700; font-size: 14px; }
.rank-to-next { font-size: 12px; font-weight: 500; }
.rankup {
  font-weight: 800;
  font-size: 16px;
  text-align: center;
  animation: pop-in 0.45s cubic-bezier(0.34, 1.56, 0.64, 1);
}
/* the celebratory bar: molten gradient, breathing glow, hot leading edge */
.rank-track { overflow: visible; }
.rank-sweep {
  position: relative;
  min-width: 12px;
  border-radius: 99px;
  transition: none; /* overrides the global 0.4s - no motion until armed */
  background: linear-gradient(90deg, var(--accent-2), var(--accent), #ffd166, var(--accent), var(--accent-2));
  background-size: 300% 100%;
  animation: molten 2.4s linear infinite, bar-glow 1.8s ease-in-out infinite;
}
.rank-sweep.sweeping { transition: width 1.6s cubic-bezier(0.16, 1, 0.3, 1); }
.rank-sweep::after {
  content: '';
  position: absolute;
  right: -2px;
  top: 50%;
  width: 16px;
  height: 16px;
  transform: translate(35%, -50%);
  border-radius: 50%;
  background: radial-gradient(circle, #fff8e7 0%, var(--accent) 45%, transparent 72%);
  animation: spark-pulse 1.1s ease-in-out infinite;
}
@keyframes molten {
  from { background-position: 0% 0; }
  to { background-position: -300% 0; }
}
@keyframes bar-glow {
  0%, 100% { box-shadow: 0 0 6px 0 color-mix(in srgb, var(--accent) 45%, transparent); }
  50% { box-shadow: 0 0 16px 2px color-mix(in srgb, var(--accent) 80%, transparent); }
}
@keyframes spark-pulse {
  0%, 100% { opacity: 0.75; transform: translate(35%, -50%) scale(0.85); }
  50% { opacity: 1; transform: translate(35%, -50%) scale(1.15); }
}
.freeze-note { font-size: 13px; font-weight: 600; }

.streak-note { font-weight: 700; color: var(--accent-2); animation: flame-pulse 1.6s ease-in-out infinite; }
@keyframes flame-pulse {
  0%, 100% { transform: scale(1); }
  50% { transform: scale(1.06); }
}

/* tomorrow's appointment */
.tomorrow {
  text-align: left;
  background: var(--bg-soft);
  border-radius: var(--radius-sm);
  padding: 14px 16px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.tomorrow-line { font-size: 14px; line-height: 1.5; }
.tomorrow-line b { color: var(--accent); }
.tomorrow-q { font-size: 13.5px; font-weight: 700; }
.slot-row { display: flex; gap: 8px; flex-wrap: wrap; }
.slot-chip {
  flex: 1;
  min-width: 90px;
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: var(--radius-pill);
  padding: 9px 12px;
  font-family: inherit;
  font-size: 13px;
  font-weight: 700;
  color: var(--text);
  cursor: pointer;
  transition: border-color 0.15s ease, background 0.15s ease;
}
.slot-chip:hover { border-color: var(--accent); background: var(--accent-soft); }
.tomorrow-set { font-size: 13px; }

/* the trial pitch after the celebration */
.upsell {
  position: relative;
  text-align: left;
  display: flex;
  flex-direction: column;
  gap: 10px;
  border-color: rgba(245, 158, 11, 0.35);
  background: var(--accent-soft);
}
.upsell-x {
  position: absolute;
  top: 6px;
  right: 10px;
  background: none;
  border: none;
  font-size: 20px;
  line-height: 1;
  color: var(--text-dim);
  cursor: pointer;
  padding: 4px;
}
.upsell-x:hover { color: var(--text); }
.upsell-head {
  display: flex;
  align-items: center;
  gap: 7px;
  font-weight: 800;
  font-size: 15px;
  color: var(--accent);
}
.upsell-ico { width: 16px; height: 16px; flex-shrink: 0; }
.upsell-text { font-size: 14px; line-height: 1.5; }
.upsell-try {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  font-size: 13px;
  font-weight: 600;
}
.upsell-try:hover { color: var(--text); }
.upsell-try-ico { width: 14px; height: 14px; flex-shrink: 0; }

/* recap */
.recap {
  text-align: left;
  background: var(--bg-soft);
  border-radius: var(--radius-sm);
  padding: 14px 16px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.recap-title {
  font-size: 13px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--text-dim);
}
.recap-row { display: flex; align-items: flex-start; gap: 10px; }
.recap-play {
  background: none;
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 6px 9px;
  font-size: 13px;
  cursor: pointer;
  flex-shrink: 0;
}
.recap-play:hover { border-color: var(--accent); }
.recap-fi { font-weight: 700; font-size: 15px; }
.recap-en { font-size: 13px; margin-top: 1px; }

/* confetti */
.confetti { position: absolute; inset: 0; pointer-events: none; }
.confetti-piece {
  position: absolute;
  top: -12px;
  width: 8px;
  height: 12px;
  border-radius: 2px;
  opacity: 0;
  animation-name: confetti-fall;
  animation-timing-function: ease-in;
  animation-iteration-count: 1;
  animation-fill-mode: forwards;
}
@keyframes confetti-fall {
  0% { opacity: 1; transform: translateY(0) rotate(0deg); }
  80% { opacity: 0.9; }
  100% { opacity: 0; transform: translateY(420px) rotate(540deg); }
}
</style>
