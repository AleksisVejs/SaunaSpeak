<script setup>
import { onMounted, onUnmounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import api from '../api'
import SentenceCard from '../components/SentenceCard.vue'
import PatternNote from '../components/PatternNote.vue'
import { useFinnishAudio } from '../composables/useFinnishAudio'

const route = useRoute()
const lesson = ref(null)
const loading = ref(true)
const error = ref('')

const { playSentenceAsync, stop } = useFinnishAudio()

// Listening mode: the whole lesson back to back — input flooding for the ears.
const playingId = ref(null)
let playToken = 0 // bumping this cancels an in-flight play-all loop

onMounted(async () => {
  try {
    const { data } = await api.get(`/lessons/${route.params.id}`)
    lesson.value = data.lesson
  } catch {
    error.value = 'Could not load this lesson.'
  } finally {
    loading.value = false
  }
})

async function playAll() {
  const token = ++playToken
  for (const s of lesson.value.sentences) {
    if (token !== playToken) return
    playingId.value = s.id
    document.getElementById(`sentence-${s.id}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' })
    await playSentenceAsync(s.finnish_text, s.audio_url)
    if (token !== playToken) return
    // A breath between lines, like real speech.
    await new Promise((r) => setTimeout(r, 650))
  }
  if (token === playToken) playingId.value = null
}

function stopPlayAll() {
  playToken++
  stop()
  playingId.value = null
}

onUnmounted(stopPlayAll)
</script>

<template>
  <!-- single root element — required by the page transition in App.vue -->
  <div>
  <div v-if="loading" class="spinner"></div>
  <div v-else-if="error" class="error-msg">{{ error }}</div>

  <div v-else class="lesson-page">
    <router-link to="/dashboard" class="back">‹ Back</router-link>
    <div class="lesson-head">
      <span class="lesson-level">{{ lesson.level }}</span>
      <h2>{{ lesson.title }}</h2>
      <p class="muted">{{ lesson.sentences.length }} sentences</p>
    </div>

    <button class="btn btn-ghost btn-block listen-all" @click="playingId ? stopPlayAll() : playAll()">
      {{ playingId ? '⏹ Stop listening' : '🎧 Listen to the whole lesson' }}
    </button>

    <PatternNote v-if="lesson.pattern" :pattern="lesson.pattern" class="lesson-pattern" />

    <div class="sentence-list">
      <SentenceCard
        v-for="s in lesson.sentences"
        :id="`sentence-${s.id}`"
        :key="s.id"
        :sentence="s"
        :status="s.status"
        :class="{ 'now-playing': playingId === s.id }"
      />
    </div>
  </div>
  </div>
</template>

<style scoped>
.back { display: inline-block; color: var(--text-dim); font-size: 14px; margin-bottom: 14px; }
.back:hover { color: var(--text); }
.lesson-head { margin-bottom: 20px; }
.lesson-head h2 { font-size: 24px; margin: 8px 0 4px; }
.lesson-level {
  font-size: 11px;
  font-weight: 700;
  color: var(--accent);
  background: var(--accent-soft);
  padding: 2px 8px;
  border-radius: 99px;
}
.listen-all { margin-bottom: 18px; font-size: 15px; }
.lesson-pattern { margin-bottom: 18px; }
.sentence-list { display: flex; flex-direction: column; gap: 14px; }
.sentence-list :deep(.now-playing) {
  border-color: var(--accent);
  box-shadow: 0 0 0 3px var(--accent-soft);
}
</style>
