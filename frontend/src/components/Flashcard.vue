<script setup>
// Anki-style flip card. Front prompts recall; tap/space flips to the answer.
// direction 'fi-en' = see Finnish, recall meaning; 'en-fi' = see meaning, recall Finnish.
import { computed, watch } from 'vue'
import { useFinnishAudio } from '../composables/useFinnishAudio'

const props = defineProps({
  card: { type: Object, required: true },
  direction: { type: String, default: 'fi-en' },
  flipped: { type: Boolean, default: false }
})
const emit = defineEmits(['flip'])

const { playWord } = useFinnishAudio()

const hasGloss = computed(() => !!props.card.gloss)
// With no gloss we can only prompt from the Finnish side.
const dir = computed(() => (hasGloss.value ? props.direction : 'fi-en'))

const frontText = computed(() => (dir.value === 'fi-en' ? props.card.word : props.card.gloss))
const frontLang = computed(() => (dir.value === 'fi-en' ? 'Finnish' : 'English'))
const backWord = computed(() => props.card.word)
const backGloss = computed(() => props.card.gloss)

function play() {
  playWord(props.card.word)
}

// Every reveal is a listening rep: hear the word the moment the answer shows.
watch(
  () => props.flipped,
  (flipped) => {
    if (flipped) play()
  }
)
</script>

<template>
  <div class="flashcard" :class="{ flipped }" @click="emit('flip')">
    <div class="fc-inner">
      <!-- front: the prompt -->
      <div class="fc-face fc-front">
        <span class="fc-lang">{{ frontLang }}</span>
        <p class="fc-word">{{ frontText }}</p>
        <button v-if="dir === 'fi-en'" class="fc-audio" @click.stop="play" aria-label="Play word">🔊</button>
        <span class="fc-hint">Tap to reveal</span>
      </div>

      <!-- back: the answer -->
      <div class="fc-face fc-back">
        <button class="fc-audio" @click.stop="play" aria-label="Play word">🔊</button>
        <p class="fc-word">{{ backWord }}</p>
        <p v-if="backGloss" class="fc-gloss">{{ backGloss }}</p>
      </div>
    </div>
  </div>
</template>

<style scoped>
.flashcard {
  perspective: 1200px;
  cursor: pointer;
  user-select: none;
}
.fc-inner {
  position: relative;
  width: 100%;
  min-height: 240px;
  transition: transform 0.5s var(--ease);
  transform-style: preserve-3d;
}
.flashcard.flipped .fc-inner { transform: rotateY(180deg); }

.fc-face {
  position: absolute;
  inset: 0;
  backface-visibility: hidden;
  -webkit-backface-visibility: hidden;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 14px;
  padding: 28px;
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  text-align: center;
}
.fc-back { transform: rotateY(180deg); background: var(--bg-soft); }

.fc-lang {
  font-size: var(--text-xs);
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: var(--text-faint);
}
.fc-word { font-size: 30px; font-weight: 800; line-height: 1.2; }
.fc-gloss { color: var(--text-dim); font-size: 16px; }
.fc-hint { font-size: var(--text-xs); color: var(--text-faint); margin-top: 4px; }
.fc-audio {
  background: var(--accent-soft);
  color: var(--accent);
  border: none;
  border-radius: var(--radius-pill);
  width: 42px;
  height: 42px;
  font-size: 18px;
  cursor: pointer;
}
</style>
