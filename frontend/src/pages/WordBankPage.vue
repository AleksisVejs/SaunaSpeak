<script setup>
// The personal word bank: every word tapped in a lesson or session lands
// here and gets its own flashcard schedule. The page groups words by where
// they are in that schedule (due / learning / mastered) so a growing bank
// stays calm instead of becoming one long pile.
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import api from '../api'
import { useFinnishAudio } from '../composables/useFinnishAudio'

const { playWord, playSentence } = useFinnishAudio()

const words = ref([])
const dueCount = ref(0)
const loading = ref(true)
const error = ref('')
const search = ref('')

onMounted(async () => {
  try {
    const { data } = await api.get('/words')
    words.value = data.words
    dueCount.value = data.due_count ?? 0
  } catch {
    error.value = 'Could not load your word bank.'
  } finally {
    loading.value = false
  }
})

function isDue(w) {
  return !w.next_review_at || new Date(w.next_review_at) <= new Date()
}

// "in 3d" - when a scheduled word comes back. Kept tiny on purpose.
function backIn(w) {
  if (!w.next_review_at) return ''
  const days = Math.ceil((new Date(w.next_review_at) - Date.now()) / 86400000)
  if (days <= 0) return ''
  return days === 1 ? 'back tomorrow' : `back in ${days}d`
}

const filtered = computed(() => {
  const q = search.value.trim().toLowerCase()
  if (!q) return words.value
  return words.value.filter(
    (w) => w.word.toLowerCase().includes(q) || (w.gloss ?? '').toLowerCase().includes(q)
  )
})

// Three calm buckets instead of one long list or per-row status jargon.
const groups = computed(() =>
  [
    { key: 'due', title: '🔔 Due now', words: filtered.value.filter((w) => isDue(w)) },
    {
      key: 'learning',
      title: '🌱 Still learning',
      words: filtered.value.filter((w) => !isDue(w) && w.status !== 'mastered')
    },
    { key: 'mastered', title: '🏅 Mastered', words: filtered.value.filter((w) => !isDue(w) && w.status === 'mastered') }
  ].filter((g) => g.words.length)
)

// Reassurance when the deck is empty: say when it comes back.
const nextReviewText = computed(() => {
  const upcoming = words.value
    .map((w) => (w.next_review_at ? new Date(w.next_review_at) : null))
    .filter((d) => d && d > new Date())
    .sort((a, b) => a - b)[0]
  if (!upcoming) return ''
  const days = Math.ceil((upcoming - Date.now()) / 86400000)
  return days <= 1 ? 'tomorrow' : `in ${days} days`
})

// Removing is deferred behind an undo snackbar: the DELETE only fires after
// the window closes, so an undone remove keeps the word's review history.
const pending = ref(null) // { word, index, timer }

function commitPending() {
  if (!pending.value) return
  clearTimeout(pending.value.timer)
  const { word } = pending.value
  pending.value = null
  api.delete(`/words/${word.id}`).catch(() => {
    words.value = [...words.value, word] // restore on failure
  })
}

function remove(word) {
  commitPending() // only one undo window at a time
  const index = words.value.findIndex((w) => w.id === word.id)
  words.value = words.value.filter((w) => w.id !== word.id)
  pending.value = { word, index, timer: setTimeout(commitPending, 5000) }
}

function undoRemove() {
  if (!pending.value) return
  clearTimeout(pending.value.timer)
  const { word, index } = pending.value
  pending.value = null
  const next = [...words.value]
  next.splice(Math.min(index, next.length), 0, word)
  words.value = next
}

onBeforeUnmount(commitPending)
</script>

<template>
  <!-- single root element - required by the page transition in App.vue -->
  <div>
    <div v-if="loading" class="spinner"></div>
    <div v-else-if="error" class="error-msg">{{ error }}</div>

    <div v-else class="wordbank">
      <router-link to="/dashboard" class="back">‹ Back</router-link>

      <div class="head">
        <h2>⭐ My Word Bank <span v-if="words.length" class="head-count muted">{{ words.length }} words</span></h2>
        <p class="muted">
          Every word you tap gets collected here, and flashcards bring each one
          back right before you'd forget it.
        </p>
      </div>

      <router-link v-if="dueCount" to="/words/review" class="btn btn-primary btn-block review-cta">
        🎴 Review {{ dueCount }} {{ dueCount === 1 ? 'card' : 'cards' }}
      </router-link>

      <div v-else-if="words.length" class="card caught-up">
        ✓ All caught up<span v-if="nextReviewText"> — next review {{ nextReviewText }}</span>
      </div>

      <div v-if="!words.length" class="card empty">
        <img class="empty-icon vaino" src="/vaino-point.png" alt="Väinö pointing" />
        <p>Nothing here yet.</p>
        <p class="muted">Tap any Finnish word in a lesson or session to hear it and save it automatically.</p>
      </div>

      <template v-else>
        <input
          v-if="words.length > 8"
          v-model="search"
          type="search"
          class="search"
          placeholder="Search your words…"
          aria-label="Search your words"
        />

        <p v-if="search && !filtered.length" class="muted no-match">No words match "{{ search }}".</p>

        <section v-for="g in groups" :key="g.key" class="group">
          <h3 class="group-title">
            {{ g.title }} <span class="group-count muted">{{ g.words.length }}</span>
          </h3>
          <div class="word-list">
            <div v-for="word in g.words" :key="word.id" class="card word-row">
              <button class="play" :aria-label="`Play ${word.word}`" :title="`Play ${word.word}`" @click="playWord(word.word)">🔊</button>
              <div class="texts">
                <p class="word">{{ word.word }}</p>
                <p v-if="word.gloss" class="gloss muted">{{ word.gloss }}</p>
                <button
                  v-if="word.sentence"
                  class="context"
                  :title="'Play: ' + word.sentence.finnish_text"
                  @click="playSentence(word.sentence.finnish_text, word.sentence.audio_url)"
                >
                  ”{{ word.sentence.finnish_text }}”
                </button>
              </div>
              <div class="side">
                <span v-if="backIn(word)" class="back-in muted">{{ backIn(word) }}</span>
                <button class="remove" aria-label="Remove from word bank" title="Remove from word bank" @click="remove(word)">✕</button>
              </div>
            </div>
          </div>
        </section>
      </template>

      <transition name="fade">
        <div v-if="pending" class="snackbar" role="status">
          <span>Removed “{{ pending.word.word }}”</span>
          <button class="undo" @click="undoRemove">Undo</button>
        </div>
      </transition>
    </div>
  </div>
</template>

<style scoped>
.back { display: inline-block; color: var(--text-dim); font-size: 14px; margin-bottom: 14px; }
.back:hover { color: var(--text); }
.head { margin-bottom: 20px; }
.head h2 { font-size: 24px; margin-bottom: 6px; display: flex; align-items: baseline; gap: 10px; flex-wrap: wrap; }
.head-count { font-size: 13px; font-weight: 600; }
.head .muted { line-height: 1.5; }
.review-cta { margin-bottom: 18px; font-size: 16px; }

.caught-up {
  text-align: center;
  font-size: 14px;
  font-weight: 700;
  color: var(--green);
  background: var(--green-soft);
  border-color: var(--green);
  padding: 12px 16px;
  margin-bottom: 18px;
}

.empty { text-align: center; padding: 34px 22px; display: flex; flex-direction: column; gap: 8px; }
.empty-icon.vaino { width: 120px; height: 120px; margin: 0 auto; }

.search {
  width: 100%;
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  color: var(--text);
  font-family: inherit;
  font-size: 15px;
  padding: 10px 14px;
  margin-bottom: 16px;
}
.search:focus { outline: none; border-color: var(--accent); }
.no-match { text-align: center; margin: 18px 0; }

.group { margin-bottom: 20px; }
.group-title {
  font-size: 13px;
  font-weight: 800;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--text-dim);
  margin-bottom: 8px;
  display: flex;
  align-items: baseline;
  gap: 7px;
}
.group-count { font-size: 12px; font-weight: 600; }

.word-list { display: flex; flex-direction: column; gap: 10px; }
.word-row { display: flex; align-items: center; gap: 14px; padding: 12px 14px; }
.play {
  background: none;
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 8px 10px;
  font-size: 14px;
  cursor: pointer;
  flex-shrink: 0;
}
.play:hover { border-color: var(--accent); }
.texts { flex: 1; min-width: 0; }
.word { font-weight: 800; font-size: 17px; }
.gloss { font-size: 13px; margin-top: 2px; line-height: 1.4; }
.context {
  display: block;
  background: none;
  border: none;
  padding: 0;
  margin-top: 4px;
  font-family: inherit;
  font-size: 12.5px;
  font-style: italic;
  color: var(--text-faint);
  cursor: pointer;
  text-align: left;
  line-height: 1.4;
}
.context:hover { color: var(--accent); }

.side {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 6px;
  flex-shrink: 0;
}
.back-in { font-size: 11px; white-space: nowrap; }
.remove {
  background: none;
  border: 1px solid var(--border);
  border-radius: 8px;
  color: var(--text-dim);
  font-family: inherit;
  font-size: 12px;
  font-weight: 700;
  line-height: 1;
  padding: 6px 8px;
  cursor: pointer;
}
.remove:hover { border-color: var(--red); color: var(--red); }

.snackbar {
  position: fixed;
  left: 50%;
  bottom: 24px;
  transform: translateX(-50%);
  display: flex;
  align-items: center;
  gap: 16px;
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: var(--radius-pill);
  box-shadow: 0 6px 24px rgba(0, 0, 0, 0.25);
  padding: 10px 18px;
  font-size: 14px;
  font-weight: 600;
  z-index: 50;
}
.undo {
  background: none;
  border: none;
  color: var(--accent);
  font-family: inherit;
  font-size: 14px;
  font-weight: 800;
  cursor: pointer;
  padding: 0;
}
.fade-enter-active,
.fade-leave-active { transition: opacity 0.2s ease, transform 0.2s ease; }
.fade-enter-from,
.fade-leave-to { opacity: 0; transform: translateX(-50%) translateY(8px); }
</style>
