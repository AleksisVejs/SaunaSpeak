<script setup>
import { onMounted, ref } from 'vue'
import api from '../api'
import { useFinnishAudio } from '../composables/useFinnishAudio'

const { playWord } = useFinnishAudio()

const words = ref([])
const dueCount = ref(0)
const loading = ref(true)
const error = ref('')

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

async function remove(word) {
  words.value = words.value.filter((w) => w.id !== word.id)
  try {
    await api.delete(`/words/${word.id}`)
  } catch {
    words.value = [...words.value, word] // restore on failure
  }
}
</script>

<template>
  <!-- single root element — required by the page transition in App.vue -->
  <div>
    <div v-if="loading" class="spinner"></div>
    <div v-else-if="error" class="error-msg">{{ error }}</div>

    <div v-else class="wordbank">
      <router-link to="/dashboard" class="back">‹ Back</router-link>

      <div class="head">
        <h2>⭐ My Word Bank</h2>
        <p class="muted">
          Every word you tap gets collected here — they're the ones your memory flagged as hard.
          Read them aloud now and then; deleting one you've truly learned feels great.
        </p>
      </div>

      <router-link v-if="dueCount" to="/words/review" class="btn btn-primary btn-block review-cta">
        🎴 Review {{ dueCount }} {{ dueCount === 1 ? 'card' : 'cards' }}
      </router-link>

      <div v-if="!words.length" class="card empty">
        <div class="empty-icon">👆</div>
        <p>Nothing here yet.</p>
        <p class="muted">Tap any Finnish word in a lesson or session to hear it and save it automatically.</p>
      </div>

      <div v-else class="word-list">
        <div v-for="word in words" :key="word.id" class="card word-row">
          <button class="play" :title="`Play ${word.word}`" @click="playWord(word.word)">🔊</button>
          <div class="texts">
            <p class="word">{{ word.word }}</p>
            <p v-if="word.gloss" class="gloss muted">{{ word.gloss }}</p>
          </div>
          <button class="remove" title="I know this word now" @click="remove(word)">✓ Learned</button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.back { display: inline-block; color: var(--text-dim); font-size: 14px; margin-bottom: 14px; }
.back:hover { color: var(--text); }
.head { margin-bottom: 20px; }
.head h2 { font-size: 24px; margin-bottom: 6px; }
.head .muted { line-height: 1.5; }
.review-cta { margin-bottom: 18px; font-size: 16px; }
.empty { text-align: center; padding: 34px 22px; display: flex; flex-direction: column; gap: 8px; }
.empty-icon { font-size: 40px; }
.word-list { display: flex; flex-direction: column; gap: 10px; }
.word-row { display: flex; align-items: center; gap: 14px; padding: 14px 16px; }
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
.remove {
  background: none;
  border: 1px solid var(--border);
  border-radius: 8px;
  color: var(--text-dim);
  font-family: inherit;
  font-size: 12px;
  font-weight: 600;
  padding: 7px 10px;
  cursor: pointer;
  flex-shrink: 0;
  white-space: nowrap;
}
.remove:hover { border-color: var(--green); color: var(--green); }
</style>
