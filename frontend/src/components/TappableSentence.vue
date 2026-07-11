<script setup>
import { computed, ref, watch } from 'vue'
import api from '../api'
import { useFinnishAudio } from '../composables/useFinnishAudio'

const { playWord } = useFinnishAudio()

const props = defineProps({
  text: { type: String, required: true },
  glosses: { type: Object, default: null },
  sentenceId: { type: [Number, String], default: null }
})

const selected = ref(null) // { index, word, gloss, saved }

// Session-wide dedupe so repeat taps don't spam the API.
const sentWords = new Set()

watch(
  () => props.text,
  () => (selected.value = null)
)

// "Moi!" → { pre: '', word: 'Moi', post: '!' } - punctuation stays outside the tap target.
const tokens = computed(() =>
  props.text.split(/\s+/).map((chunk, index) => {
    const m = chunk.match(/^([^\p{L}\p{N}]*)([\p{L}\p{N}'-]+)?([^\p{L}\p{N}]*)$/u)
    return {
      index,
      pre: m?.[1] ?? '',
      word: m?.[2] ?? chunk,
      post: m?.[3] ?? ''
    }
  })
)

function tap(token) {
  if (selected.value?.index === token.index) {
    selected.value = null
    return
  }
  const key = token.word.toLowerCase()
  const gloss = props.glosses?.[key] ?? null
  selected.value = {
    index: token.index,
    word: token.word,
    gloss: gloss ?? 'No gloss for this word yet.',
    saved: sentWords.has(key)
  }
  playWord(token.word)
  if (gloss) collect(key, gloss)
}

// Tapped words are the hard ones - collect them into the personal word bank.
async function collect(word, gloss) {
  if (sentWords.has(word)) return
  sentWords.add(word)
  try {
    await api.post('/words', { word, gloss, sentence_id: props.sentenceId })
    if (selected.value?.word.toLowerCase() === word) selected.value.saved = true
  } catch {
    sentWords.delete(word) // let a later tap retry
  }
}
</script>

<template>
  <div class="tappable">
    <p class="sentence">
      <template v-for="token in tokens" :key="token.index">{{ token.pre }}<button
        class="word"
        :class="{ active: selected?.index === token.index }"
        :aria-label="`${token.word} - hear it and see its meaning`"
        @click="tap(token)"
      >{{ token.word }}</button>{{ token.post }}{{ ' ' }}</template>
    </p>

    <transition name="fade">
      <div v-if="selected" class="gloss-panel">
        <button class="gloss-word" @click="playWord(selected.word)">🔊 {{ selected.word }}</button>
        <p class="gloss-text">
          {{ selected.gloss }}
          <span v-if="selected.saved" class="saved-note">⭐ in your word bank</span>
        </p>
        <button class="gloss-close" @click="selected = null">✕</button>
      </div>
    </transition>
  </div>
</template>

<style scoped>
.tappable { display: flex; flex-direction: column; gap: 12px; }
.sentence { line-height: 1.45; }
.word {
  background: none;
  border: none;
  padding: 0;
  margin: 0;
  font: inherit;
  color: inherit;
  cursor: pointer;
  border-radius: 4px;
  text-decoration: underline dotted var(--text-dim) 1.5px;
  text-underline-offset: 5px;
  transition: color 0.12s ease, background 0.12s ease;
}
.word:hover { color: var(--accent); }
.word.active {
  color: var(--accent);
  background: var(--accent-soft);
  text-decoration-color: var(--accent);
}
.gloss-panel {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  background: var(--bg-soft);
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  padding: 12px 14px;
}
.gloss-word {
  background: none;
  border: none;
  padding: 0;
  font-family: inherit;
  font-size: 15px;
  font-weight: 700;
  color: var(--accent);
  cursor: pointer;
  white-space: nowrap;
}
.gloss-text { flex: 1; color: var(--text-dim); font-size: 14px; line-height: 1.45; }
.saved-note { display: block; color: var(--accent); font-size: 12px; font-weight: 600; margin-top: 3px; }
.gloss-close {
  background: none;
  border: none;
  color: var(--text-dim);
  font-size: 13px;
  cursor: pointer;
  padding: 2px;
}
.gloss-close:hover { color: var(--text); }
</style>
