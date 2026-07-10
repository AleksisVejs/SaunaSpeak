<script setup>
// Anki-style flashcard review over the word bank. Flip → self-grade → the
// backend reschedules each card with spaced repetition.
import { onMounted, onUnmounted, ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import api from '../api'
import Flashcard from '../components/Flashcard.vue'

const router = useRouter()

const cards = ref([])
const loading = ref(true)
const index = ref(0)
const flipped = ref(false)
const direction = ref('fi-en')
const reviewed = ref(0)
const submitting = ref(false)

const current = computed(() => cards.value[index.value] || null)
const finished = computed(() => !loading.value && (cards.value.length === 0 || index.value >= cards.value.length))
const progressPct = computed(() => (cards.value.length ? Math.round((index.value / cards.value.length) * 100) : 0))

onMounted(async () => {
  try {
    const { data } = await api.get('/words/review')
    cards.value = data.cards
  } finally {
    loading.value = false
  }
  window.addEventListener('keydown', onKey)
})

onUnmounted(() => window.removeEventListener('keydown', onKey))

function flip() {
  flipped.value = !flipped.value
}

async function grade(g) {
  if (!current.value || submitting.value) return
  submitting.value = true
  const card = current.value
  try {
    await api.post(`/words/${card.id}/grade`, { grade: g })
    reviewed.value++
    index.value++
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
      <h1>{{ reviewed ? 'Deck cleared!' : 'No cards due' }}</h1>
      <p class="muted">
        {{ reviewed
          ? `You reviewed ${reviewed} ${reviewed === 1 ? 'card' : 'cards'}. They'll come back right before you'd forget them.`
          : 'Tap words in lessons and sessions to fill your deck — they resurface here as flashcards.' }}
      </p>
      <router-link to="/words" class="btn btn-primary btn-block">Back to word bank</router-link>
      <button class="btn btn-ghost btn-block" @click="router.push('/dashboard')">Home</button>
    </div>

    <!-- active review -->
    <div v-else class="deck">
      <div class="review-top">
        <button class="quit" @click="router.push('/words')" aria-label="Quit">✕</button>
        <div class="progress-track"><div class="progress-fill" :style="{ width: progressPct + '%' }"></div></div>
        <span class="counter">{{ index + 1 }}/{{ cards.length }}</span>
      </div>

      <div class="dir">
        <button :class="{ on: direction === 'fi-en' }" @click="direction = 'fi-en'">🇫🇮 → EN</button>
        <button :class="{ on: direction === 'en-fi' }" @click="direction = 'en-fi'">EN → 🇫🇮</button>
      </div>

      <Flashcard :card="current" :direction="direction" :flipped="flipped" @flip="flip" />

      <div class="controls">
        <button v-if="!flipped" class="btn btn-primary btn-block" @click="flip">Show answer</button>
        <div v-else class="grades">
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
      </div>
    </div>
  </div>
</template>

<style scoped>
.review { min-height: 100vh; display: flex; flex-direction: column; padding: max(16px, 3vh) 4px 24px; }

.review-top { display: flex; align-items: center; gap: 12px; margin-bottom: 18px; }
.quit { background: none; border: none; color: var(--text-dim); font-size: 20px; cursor: pointer; font-family: inherit; }
.review-top .progress-track { flex: 1; }
.counter { font-size: 13px; color: var(--text-dim); font-weight: 600; white-space: nowrap; }

.dir { display: flex; gap: 8px; justify-content: center; margin-bottom: 18px; }
.dir button {
  background: var(--card);
  border: 1px solid var(--border);
  color: var(--text-dim);
  border-radius: var(--radius-pill);
  padding: 6px 14px;
  font-family: inherit;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
}
.dir button.on { border-color: var(--accent); color: var(--accent); background: var(--accent-soft); }

.controls { margin-top: 22px; }
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

.finish { margin: auto 0; text-align: center; display: flex; flex-direction: column; gap: 12px; }
.finish-icon { font-size: 56px; }
.finish-icon.vaino { width: 132px; height: 132px; margin: 0 auto; }
.finish h1 { font-size: 26px; }
.finish .muted { line-height: 1.55; margin-bottom: 8px; }
</style>
