<script setup>
// Flashcard review over the learner's own chat mistakes. Every correction a
// character hands out in Sauna Chat lands here as a card: front shows what
// you said, you produce the natural spoken form, the back confirms it with
// audio. Retrieval practice on your own errors - the strongest kind.
import { onMounted, onUnmounted, ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { Check, Pencil, Volume2, X } from 'lucide-vue-next'
import LoylyIcon from '../components/icons/LoylyIcon.vue'
import api from '../api'
import { useFinnishAudio } from '../composables/useFinnishAudio'

const router = useRouter()
const { playSpoken } = useFinnishAudio()

const cards = ref([])
const loading = ref(true)
const index = ref(0)
const flipped = ref(false)
const reviewed = ref(0)
const submitting = ref(false)

const current = computed(() => cards.value[index.value] || null)
const finished = computed(() => !loading.value && (cards.value.length === 0 || index.value >= cards.value.length))
const progressPct = computed(() => (cards.value.length ? Math.round((index.value / cards.value.length) * 100) : 0))

onMounted(async () => {
  try {
    const { data } = await api.get('/mistakes/review')
    cards.value = data.cards
  } finally {
    loading.value = false
  }
  window.addEventListener('keydown', onKey)
})

onUnmounted(() => window.removeEventListener('keydown', onKey))

function flip() {
  flipped.value = !flipped.value
  // Every reveal is a listening rep: hear the natural form immediately.
  if (flipped.value && current.value) playSpoken(current.value.corrected)
}

async function grade(g) {
  if (!current.value || submitting.value) return
  submitting.value = true
  const card = current.value
  try {
    await api.post(`/mistakes/${card.id}/grade`, { grade: g })
    reviewed.value++
    index.value++
    flipped.value = false
  } finally {
    submitting.value = false
  }
}

// An AI correction can occasionally miss - let the learner drop the card.
async function removeCard() {
  if (!current.value || submitting.value) return
  submitting.value = true
  const card = current.value
  try {
    await api.delete(`/mistakes/${card.id}`)
    cards.value = cards.value.filter((c) => c.id !== card.id)
    flipped.value = false
  } finally {
    submitting.value = false
  }
}

function onKey(e) {
  if (finished.value) return
  if (e.code === 'Space') {
    e.preventDefault()
    flip()
  } else if (flipped.value) {
    if (e.key === '1') grade('again')
    else if (e.key === '2') grade('good')
    else if (e.key === '3') grade('easy')
  }
}
</script>

<template>
  <div class="review">
    <div v-if="loading" class="spinner"></div>

    <!-- finish / empty -->
    <div v-else-if="finished" class="finish">
      <img v-if="reviewed" class="finish-icon vaino" src="/vaino-cheer.png" alt="Väinö cheering with a raised fist" />
      <img v-else class="finish-icon vaino" src="/vaino-relax.png" alt="Väinö relaxing on the sauna bench" />
      <h1>{{ reviewed ? 'Mistakes cleared!' : 'No mistakes due' }}</h1>
      <p class="muted">
        {{ reviewed
          ? `You reworked ${reviewed} ${reviewed === 1 ? 'sentence' : 'sentences'} from your chats. Each one comes back right before you'd slip again.`
          : 'When Väinö or a Situation character corrects you in chat, the fix lands here as a flashcard.' }}
      </p>
      <router-link to="/chat" class="btn btn-primary btn-block loyly-cta"><LoylyIcon class="cta-ico" aria-hidden="true" /> To the sauna bench</router-link>
      <button class="btn btn-ghost btn-block" @click="router.push('/dashboard')">Home</button>
    </div>

    <!-- active review -->
    <div v-else class="deck">
      <div class="review-top">
        <button class="quit" @click="router.push('/dashboard')" aria-label="Quit"><X class="quit-ico" aria-hidden="true" /></button>
        <div class="progress-track"><div class="progress-fill" :style="{ width: progressPct + '%' }"></div></div>
        <span class="counter">{{ index + 1 }}/{{ cards.length }}</span>
      </div>

      <div class="mistake-card" :class="{ flipped }" @click="flip">
        <div class="mc-inner">
          <!-- front: your own words, the challenge is producing the fix -->
          <div class="mc-face mc-front">
            <span class="mc-label"><Pencil class="mc-ico" aria-hidden="true" /> You said</span>
            <p class="mc-attempt">“{{ current.attempt }}”</p>
            <p class="mc-task">How would a Finn say it?</p>
            <span class="mc-hint">Say it out loud, then tap to check</span>
          </div>

          <!-- back: the natural spoken form, with audio -->
          <div class="mc-face mc-back">
            <span class="mc-label good"><Check class="mc-ico" aria-hidden="true" /> The natural way</span>
            <p class="mc-corrected">{{ current.corrected }}</p>
            <button class="mc-audio" aria-label="Play the corrected sentence" @click.stop="playSpoken(current.corrected)"><Volume2 class="mca-ico" aria-hidden="true" /></button>
            <p class="mc-yours muted">you said: “{{ current.attempt }}”</p>
          </div>
        </div>
      </div>

      <div class="controls">
        <button v-if="!flipped" class="btn btn-primary btn-block" @click="flip">Show answer</button>
        <template v-else>
          <div class="grades">
            <button class="grade again" :disabled="submitting" @click="grade('again')">
              <span class="g-label">Again</span><span class="g-key">1</span>
            </button>
            <button class="grade good" :disabled="submitting" @click="grade('good')">
              <span class="g-label">Good</span><span class="g-key">2</span>
            </button>
            <button class="grade easy" :disabled="submitting" @click="grade('easy')">
              <span class="g-label">Easy</span><span class="g-key">3</span>
            </button>
          </div>
          <button class="drop muted" :disabled="submitting" @click="removeCard">Not a real mistake? Remove card</button>
        </template>
      </div>
    </div>
  </div>
</template>

<style scoped>
.review { min-height: 100vh; min-height: 100dvh; display: flex; flex-direction: column; padding: max(16px, 3vh) 4px 24px; }

.review-top { display: flex; align-items: center; gap: 12px; margin-bottom: 18px; }
.quit { background: none; border: none; color: var(--text-dim); cursor: pointer; font-family: inherit; display: inline-flex; }
.quit-ico { width: 19px; height: 19px; }
.review-top .progress-track { flex: 1; }
.counter { font-size: 13px; color: var(--text-dim); font-weight: 600; white-space: nowrap; }

/* ---- the flip card ---- */
.mistake-card { perspective: 1200px; cursor: pointer; user-select: none; }
.mc-inner {
  position: relative;
  width: 100%;
  min-height: 260px;
  transition: transform 0.5s var(--ease);
  transform-style: preserve-3d;
}
.mistake-card.flipped .mc-inner { transform: rotateY(180deg); }

.mc-face {
  position: absolute;
  inset: 0;
  backface-visibility: hidden;
  -webkit-backface-visibility: hidden;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  padding: 28px;
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  text-align: center;
}
.mc-back { transform: rotateY(180deg); background: var(--bg-soft); }

.mc-label {
  font-size: var(--text-xs, 12px);
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: var(--text-faint);
  display: inline-flex;
  align-items: center;
  gap: 5px;
}
.mc-ico { width: 12px; height: 12px; flex-shrink: 0; }
.loyly-cta { display: flex; align-items: center; justify-content: center; gap: 7px; }
.cta-ico { width: 16px; height: 16px; flex-shrink: 0; }
.mc-label.good { color: var(--green); }
.mc-attempt { font-size: 22px; font-weight: 700; line-height: 1.35; color: var(--text-dim); }
.mc-task { font-size: 15px; font-weight: 700; color: var(--accent); }
.mc-hint { font-size: var(--text-xs, 12px); color: var(--text-faint); margin-top: 4px; }
.mc-corrected { font-size: 24px; font-weight: 800; line-height: 1.3; }
.mc-yours { font-size: 13px; line-height: 1.4; }
.mc-audio {
  background: var(--accent-soft);
  color: var(--accent);
  border: none;
  border-radius: var(--radius-pill);
  width: 42px;
  height: 42px;
  display: grid;
  place-items: center;
  cursor: pointer;
}
.mca-ico { width: 18px; height: 18px; }

/* ---- grading ---- */
.controls { margin-top: 22px; display: flex; flex-direction: column; gap: 12px; }
.grades { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; }
.grade {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 3px;
  padding: 14px 8px;
  border-radius: var(--radius-sm);
  border: 1px solid var(--border);
  background: var(--card);
  color: var(--text);
  font-family: inherit;
  font-weight: 700;
  cursor: pointer;
  transition: border-color 0.15s ease, background 0.15s ease;
}
.grade:disabled { opacity: 0.5; }
.grade .g-key { font-size: 11px; color: var(--text-faint); font-weight: 600; }
.grade.again:hover { border-color: var(--red); color: var(--red); }
.grade.good:hover { border-color: var(--accent); color: var(--accent); }
.grade.easy:hover { border-color: var(--green); color: var(--green); }

.drop {
  background: none;
  border: none;
  font-family: inherit;
  font-size: 12.5px;
  cursor: pointer;
  align-self: center;
  text-decoration: underline;
}
.drop:disabled { opacity: 0.5; }

.finish { margin: auto 0; text-align: center; display: flex; flex-direction: column; gap: 12px; }
.finish-icon.vaino { width: 132px; height: 132px; margin: 0 auto; }
.finish h1 { font-size: 26px; }
.finish .muted { line-height: 1.55; margin-bottom: 8px; }
</style>
