<script setup>
import { ref, onBeforeUnmount } from 'vue'

const props = defineProps({
  text: { type: String, required: true },
  // Pre-generated native audio (sentences.audio_url); falls back to browser TTS.
  audioUrl: { type: String, default: null },
  showSpeed: { type: Boolean, default: true }
})

const speaking = ref(false)
const rate = ref(0.8)
const ttsSupported = 'speechSynthesis' in window
const supported = ttsSupported || !!props.audioUrl

let player = null

function finnishVoice() {
  return speechSynthesis.getVoices().find((v) => v.lang.toLowerCase().startsWith('fi')) ?? null
}

function stop() {
  if (player) {
    player.pause()
    player = null
  }
  if (ttsSupported) speechSynthesis.cancel()
  speaking.value = false
}

function playTts() {
  if (!ttsSupported) return
  const utterance = new SpeechSynthesisUtterance(props.text)
  utterance.lang = 'fi-FI'
  const voice = finnishVoice()
  if (voice) utterance.voice = voice
  utterance.rate = rate.value
  utterance.onstart = () => (speaking.value = true)
  utterance.onend = () => (speaking.value = false)
  utterance.onerror = () => (speaking.value = false)
  speechSynthesis.speak(utterance)
}

function play() {
  stop()

  if (props.audioUrl) {
    player = new Audio(props.audioUrl)
    player.playbackRate = rate.value
    player.onplay = () => (speaking.value = true)
    player.onended = () => (speaking.value = false)
    // Missing/broken file → seamless TTS fallback.
    player.onerror = () => {
      speaking.value = false
      playTts()
    }
    player.play().catch(() => playTts())
    return
  }

  playTts()
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
    <div v-if="showSpeed" class="speed">
      <button
        v-for="s in [0.6, 0.8, 1.0]"
        :key="s"
        class="speed-chip"
        :class="{ active: rate === s }"
        @click="rate = s"
      >
        {{ s.toFixed(1) }}x
      </button>
    </div>
  </div>
  <p v-if="!supported" class="muted">Audio is not supported in this browser.</p>
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
