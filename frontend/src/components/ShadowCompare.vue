<script setup>
// Shadowing with self-monitoring: record your imitation, then A/B it against
// the native audio. Hearing your own voice next to the model is the feedback
// loop that makes shadowing work for prosody and pronunciation.
import { ref, watch } from 'vue'
import { Mic, Play, Square, Volume2 } from 'lucide-vue-next'
import { useFinnishAudio } from '../composables/useFinnishAudio'
import { useVoiceRecorder } from '../composables/useVoiceRecorder'

const props = defineProps({
  text: { type: String, required: true },
  audioUrl: { type: String, default: null }
})

const { playSentence } = useFinnishAudio()
const { supported, recording, takeUrl, error, start, stop, discard } = useVoiceRecorder()

const playingTake = ref(false)
let takeAudio = null

// New sentence - the old take no longer applies.
watch(
  () => props.text,
  () => {
    takeAudio?.pause()
    playingTake.value = false
    discard()
  }
)

function playTake() {
  if (!takeUrl.value) return
  takeAudio?.pause()
  takeAudio = new Audio(takeUrl.value)
  playingTake.value = true
  takeAudio.onended = () => (playingTake.value = false)
  takeAudio.play().catch(() => (playingTake.value = false))
}
</script>

<template>
  <div v-if="supported" class="shadow-compare">
    <button
      class="rec-btn"
      :class="{ recording }"
      @click="recording ? stop() : start()"
    >
      <template v-if="recording"><Square class="sc-ico" aria-hidden="true" /> Stop</template>
      <template v-else><Mic class="sc-ico" aria-hidden="true" /> Record yourself</template>
    </button>

    <template v-if="takeUrl && !recording">
      <button class="ab-btn" :class="{ active: playingTake }" @click="playTake">
        <Play class="sc-ico" aria-hidden="true" /> Your take
      </button>
      <button class="ab-btn" @click="playSentence(text, audioUrl)">
        <Volume2 class="sc-ico" aria-hidden="true" /> Native
      </button>
    </template>

    <p v-if="error" class="rec-error">{{ error }}</p>
  </div>
</template>

<style scoped>
.shadow-compare { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
.rec-btn,
.ab-btn {
  background: var(--bg-soft);
  border: 1px solid var(--border);
  border-radius: var(--radius-pill);
  color: var(--text-dim);
  font-family: inherit;
  font-size: 13px;
  font-weight: 700;
  padding: 8px 14px;
  cursor: pointer;
  transition: border-color 0.15s ease, color 0.15s ease;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.sc-ico { width: 13px; height: 13px; flex-shrink: 0; }
.rec-btn:hover,
.ab-btn:hover { border-color: var(--accent); color: var(--accent); }
.rec-btn.recording {
  border-color: var(--red);
  color: var(--red);
  animation: rec-pulse 1.2s ease-in-out infinite;
}
@keyframes rec-pulse {
  0%, 100% { box-shadow: 0 0 0 0 rgba(248, 113, 113, 0.35); }
  50% { box-shadow: 0 0 0 8px rgba(248, 113, 113, 0); }
}
.ab-btn.active { border-color: var(--accent); color: var(--accent); }
.rec-error { width: 100%; color: var(--red); font-size: 12px; }
</style>
