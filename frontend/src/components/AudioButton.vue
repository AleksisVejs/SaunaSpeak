<script setup>
import { ref, onBeforeUnmount } from 'vue'
import { usePrefs } from '../composables/usePrefs'

const props = defineProps({
  text: { type: String, required: true },
  // Pre-generated native audio (sentences.audio_url). Native MP3 only —
  // no browser-TTS fallback, so the voice is always the same male speaker.
  audioUrl: { type: String, default: null }
})

// Speed follows the profile setting (0.5x–2x, default 1x).
const { audioRate } = usePrefs()

const speaking = ref(false)
const supported = !!props.audioUrl

let player = null

function stop() {
  if (player) {
    player.pause()
    player = null
  }
  speaking.value = false
}

function play() {
  stop()
  if (!props.audioUrl) return

  player = new Audio(props.audioUrl)
  player.playbackRate = audioRate()
  player.onplay = () => (speaking.value = true)
  player.onended = () => (speaking.value = false)
  player.onerror = () => (speaking.value = false)
  player.play().catch(() => (speaking.value = false))
}

onBeforeUnmount(stop)

defineExpose({ play })
</script>

<template>
  <div class="audio-row">
    <button class="btn btn-ghost play-btn" :class="{ speaking }" :disabled="!supported" @click="play">
      <span v-if="speaking">🔊</span>
      <span v-else>▶</span>
      {{ speaking ? 'Playing…' : 'Play audio' }}
    </button>
  </div>
  <p v-if="!supported" class="muted">No audio for this sentence yet.</p>
</template>

<style scoped>
.audio-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
}
.play-btn { padding: 11px 18px; font-size: 15px; }
.play-btn.speaking { border-color: var(--accent); color: var(--accent); }
.speed { display: flex; gap: 6px; }
.speed-chip {
  background: var(--bg-soft);
  border: 1px solid var(--border);
  color: var(--text-dim);
  font-size: 13px;
  font-weight: 600;
  font-family: inherit;
  padding: 6px 10px;
  border-radius: 99px;
  cursor: pointer;
}
.speed-chip.active {
  background: var(--accent-soft);
  border-color: var(--accent);
  color: var(--accent);
}
</style>
