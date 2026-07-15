<script setup>
import { computed, ref, watch } from 'vue'
import { useSessionStore } from '../stores/session'
import { useAuthStore } from '../stores/auth'
import SentenceCard from '../components/SentenceCard.vue'
import PracticeInput from '../components/PracticeInput.vue'
import { cardKind, clozeWord } from '../utils/practice'
import { rankFor } from '../utils/ranks'
import { useFinnishAudio } from '../composables/useFinnishAudio'

const session = useSessionStore()
const auth = useAuthStore()
const { playSentence } = useFinnishAudio()

const card = ref(null)
const revealed = ref(false)
const submitting = ref(false)
const error = ref('')

// Load in setup (not onMounted) so the reset state is in place before the first render.
session.loadToday()

watch(
  () => session.index,
  () => (revealed.value = false)
)

// study | cloze | dictation | recall - the exercise gets harder as the SRS stage rises.
const kind = computed(() => cardKind(session.current?.status))

// Every card - including a new sentence's guess-first step - must be
// attempted (or given up on) before self-grading. Cards emit 'revealed'.
const canGrade = computed(() => revealed.value)

// Cloze checks just the missing word; the other kinds check the whole sentence.
const practiceExpected = computed(() =>
  kind.value === 'cloze' ? clozeWord(session.current.finnish_text) : session.current.finnish_text
)

// Cloze expects a single word, so the full-sentence translation would mislead
// the AI corrector there; the other kinds get the English meaning as an anchor.
const practiceTranslation = computed(() =>
  kind.value === 'cloze' ? '' : session.current.english_text || ''
)

const practiceHints = {
  study: 'Your guess - say or type it in Finnish',
  cloze: 'The missing word',
  dictation: 'What did you hear?',
  recall: 'Say or type it in Finnish'
}

function onChecked() {
  // Checking an attempt shows the answer, so reveal the card too.
  card.value?.reveal()
}

async function grade(g) {
  if (submitting.value) return
  submitting.value = true
  error.value = ''
  try {
    await session.completeCurrent(g)
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

// --- end-of-session celebration: count the XP up, sweep the rank bar ---
const totalXp = computed(() => session.xpEarned + session.bonusXp)
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
      <div class="xp-line"><span>Daily bonus</span><b>+{{ session.bonusXp }} XP</b></div>
      <div class="xp-line total"><span>Total</span><b>+{{ shownXp }} XP</b></div>
    </div>

    <div class="rank-progress">
      <p v-if="rankedUp" class="rankup">🎉 Rank up: {{ rank.icon }} <b>{{ rank.title }}</b></p>
      <p v-else class="rank-line">
        <span>{{ rank.icon }} {{ rank.title }}</span>
        <span v-if="rank.next" class="muted rank-to-next">{{ rank.next.xp - (auth.user?.xp ?? 0) }} XP to {{ rank.next.title }}</span>
      </p>
      <div class="progress-track rank-track"><div class="progress-fill rank-sweep" :class="{ sweeping: sweepOn }" :style="{ width: rankPct + '%' }"></div></div>
    </div>

    <div class="streak-note">🔥 {{ auth.user?.streak }} day streak</div>
    <p v-if="freezeEarned" class="freeze-note">❄️ Week complete — you earned a streak freeze! It auto-saves one missed day.</p>
    <p v-else-if="auth.user?.streak_freezes" class="freeze-note muted">❄️ {{ auth.user.streak_freezes }} freeze{{ auth.user.streak_freezes > 1 ? 's' : '' }} banked — each auto-saves one missed day</p>

    <div v-if="studied.length" class="recap">
      <p class="recap-title">📚 Quick recap - say each one out loud one more time</p>
      <div v-for="s in studied" :key="s.id" class="recap-row">
        <button class="recap-play" :title="'Play ' + s.finnish_text" @click="playSentence(s.finnish_text, s.audio_url)">🔊</button>
        <div class="recap-texts">
          <p class="recap-fi">{{ s.finnish_text }}</p>
          <p class="recap-en muted">{{ s.english_text }}</p>
        </div>
      </div>
    </div>

    <router-link to="/dashboard" class="btn btn-primary btn-block">Back to dashboard</router-link>
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
      <router-link to="/dashboard" class="quit">✕</router-link>
      <div class="progress-track session-progress">
        <div class="progress-fill" :style="{ width: session.progressPct + '%' }"></div>
      </div>
      <span class="counter">{{ session.index + 1 }}/{{ session.total }}</span>
    </div>

    <transition name="slide-fade" mode="out-in">
      <SentenceCard
        ref="card"
        :key="`${session.current.id}-${session.index}`"
        :sentence="session.current"
        :status="session.current.status"
        mode="study"
        @revealed="revealed = true"
      />
    </transition>

    <PracticeInput
      :key="`practice-${session.index}`"
      :expected="practiceExpected"
      :translation="practiceTranslation"
      :placeholder="practiceHints[kind]"
      @checked="onChecked"
    />

    <div v-if="error" class="error-msg">{{ error }}</div>

    <div class="grade-zone">
      <p v-if="!canGrade" class="muted grade-hint">Try it from memory, then check - or reveal the answer to grade yourself.</p>
      <div v-else class="grade-row">
        <button class="btn btn-ghost grade-btn again" :disabled="submitting" @click="grade('again')">
          🔁 Again
        </button>
        <button class="btn btn-primary grade-btn" :disabled="submitting" @click="grade('good')">
          ✓ Good
        </button>
        <button class="btn btn-ghost grade-btn easy" :disabled="submitting" @click="grade('easy')">
          ⚡ Easy
        </button>
      </div>
    </div>
  </div>
  </div>
</template>

<style scoped>
/* single root element - required by the page transition in App.vue */
.session-page { display: flex; flex-direction: column; flex: 1; }
.session { display: flex; flex-direction: column; gap: 18px; flex: 1; }
.session-top { display: flex; align-items: center; gap: 14px; }
.quit { color: var(--text-dim); font-size: 18px; padding: 4px; }
.quit:hover { color: var(--text); }
.session-progress { flex: 1; }
.counter { font-size: 13px; font-weight: 700; color: var(--text-dim); }

.grade-zone { margin-top: auto; }
.grade-hint { text-align: center; padding: 14px 0; }
.grade-row { display: grid; grid-template-columns: 1fr 1.3fr 1fr; gap: 10px; }
.grade-btn { padding: 15px 8px; font-size: 15px; }
.grade-btn.again:hover:not(:disabled) { border-color: var(--red); color: var(--red); }
.grade-btn.easy:hover:not(:disabled) { border-color: var(--green); color: var(--green); }

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
