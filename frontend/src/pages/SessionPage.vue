<script setup>
import { computed, ref, watch } from 'vue'
import { useSessionStore } from '../stores/session'
import { useAuthStore } from '../stores/auth'
import SentenceCard from '../components/SentenceCard.vue'
import PracticeInput from '../components/PracticeInput.vue'
import { cardKind, clozeWord } from '../utils/practice'
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

// study | cloze | dictation | recall — the exercise gets harder as the SRS stage rises.
const kind = computed(() => cardKind(session.current?.status))

// Every card — including a new sentence's guess-first step — must be
// attempted (or given up on) before self-grading. Cards emit 'revealed'.
const canGrade = computed(() => revealed.value)

// Cloze checks just the missing word; the other kinds check the whole sentence.
const practiceExpected = computed(() =>
  kind.value === 'cloze' ? clozeWord(session.current.finnish_text) : session.current.finnish_text
)

const practiceHints = {
  study: 'Your guess — say or type it in Finnish',
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
    if (session.finished) await auth.fetchUser()
  } catch {
    error.value = 'Something went wrong saving your progress. Try again.'
  } finally {
    submitting.value = false
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
      <div class="xp-line"><span>Daily bonus</span><b>+{{ session.bonusXp }} XP</b></div>
      <div class="xp-line total"><span>Total</span><b>+{{ session.xpEarned + session.bonusXp }} XP</b></div>
    </div>
    <div class="streak-note">🔥 {{ auth.user?.streak }} day streak</div>

    <div v-if="studied.length" class="recap">
      <p class="recap-title">📚 Quick recap — say each one out loud one more time</p>
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
      :placeholder="practiceHints[kind]"
      @checked="onChecked"
    />

    <div v-if="error" class="error-msg">{{ error }}</div>

    <div class="grade-zone">
      <p v-if="!canGrade" class="muted grade-hint">Try it from memory, then check — or reveal the answer to grade yourself.</p>
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
/* single root element — required by the page transition in App.vue */
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
  100% { opacity: 0.15; transform: translateY(420px) rotate(540deg); }
}
</style>
