<script setup>
import { computed, ref, watch } from 'vue'
import { Star, Volume2, X } from 'lucide-vue-next'
import api from '../api'
import { useFinnishAudio } from '../composables/useFinnishAudio'

const { playWord } = useFinnishAudio()

const props = defineProps({
  text: { type: String, required: true },
  glosses: { type: Object, default: null },
  sentenceId: { type: [Number, String], default: null }
})

const selected = ref(null) // { indices, word, gloss, saved }

// Session-wide dedupe so repeat taps don't spam the API.
const sentWords = new Set()

watch(
  () => props.text,
  () => (selected.value = null)
)

// "Moi!" → { pre: '', word: 'Moi', post: '!' } - punctuation stays outside the
// tap target, but -, ' and : joining two halves belong to the word: "CV:n." is
// glossed under "cv:n", so splitting it there would lose the gloss.
const tokens = computed(() =>
  props.text.split(/\s+/).map((chunk, index) => {
    const m = chunk.match(/^([^\p{L}\p{N}]*)([\p{L}\p{N}]+(?:[-':][\p{L}\p{N}]+)*)?([^\p{L}\p{N}]*)$/u)
    return {
      index,
      pre: m?.[1] ?? '',
      word: m?.[2] ?? chunk,
      post: m?.[3] ?? ''
    }
  })
)

// Some glosses cover a phrase rather than a word ("ei oo", "mul on"): the
// pieces mean nothing apart, so tapping either half selects the whole thing.
const phrases = computed(() =>
  Object.keys(props.glosses ?? {})
    .filter((key) => key.includes(' '))
    .map((key) => ({ key, parts: key.split(/\s+/) }))
)

function phraseAt(index) {
  const words = tokens.value.map((t) => t.word.toLowerCase())
  for (const { key, parts } of phrases.value) {
    for (let start = index - parts.length + 1; start <= index; start++) {
      if (start < 0) continue
      if (parts.every((part, offset) => words[start + offset] === part)) {
        return { key, indices: parts.map((_, offset) => start + offset) }
      }
    }
  }
  return null
}

function tap(token) {
  if (selected.value?.indices.includes(token.index)) {
    selected.value = null
    return
  }
  const phrase = phraseAt(token.index)
  const key = phrase?.key ?? token.word.toLowerCase()
  const gloss = props.glosses?.[key] ?? null
  selected.value = {
    indices: phrase?.indices ?? [token.index],
    word: phrase ? phrase.key : token.word,
    gloss: gloss ?? 'No gloss for this word yet.',
    saved: sentWords.has(key)
  }
  playWord(phrase ? phrase.key : token.word)
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
        :class="{ active: selected?.indices.includes(token.index) }"
        :aria-label="`${token.word} - hear it and see its meaning`"
        @click="tap(token)"
      >{{ token.word }}</button>{{ token.post }}{{ ' ' }}</template>
    </p>

    <transition name="fade">
      <div v-if="selected" class="gloss-panel">
        <button class="gloss-word" @click="playWord(selected.word)"><Volume2 class="gw-ico" aria-hidden="true" /> {{ selected.word }}</button>
        <p class="gloss-text">
          {{ selected.gloss }}
          <span v-if="selected.saved" class="saved-note"><Star class="saved-ico" aria-hidden="true" /> in your word bank</span>
        </p>
        <button class="gloss-close" aria-label="Close" @click="selected = null"><X class="close-ico" aria-hidden="true" /></button>
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
  display: inline-flex;
  align-items: center;
  gap: 5px;
}
.gw-ico { width: 14px; height: 14px; flex-shrink: 0; }
.gloss-text { flex: 1; color: var(--text-dim); font-size: 14px; line-height: 1.45; }
.saved-note { display: flex; align-items: center; gap: 4px; color: var(--accent); font-size: 12px; font-weight: 600; margin-top: 3px; }
.saved-ico { width: 12px; height: 12px; flex-shrink: 0; }
.gloss-close {
  background: none;
  border: none;
  color: var(--text-dim);
  cursor: pointer;
  padding: 2px;
}
.close-ico { width: 14px; height: 14px; display: block; }
.gloss-close:hover { color: var(--text); }
</style>
