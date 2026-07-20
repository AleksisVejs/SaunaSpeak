<script setup>
// The beginner scaffold: hear the sentence, then assemble it from word tiles.
//
// Why this exists, and why it is deliberately NOT the main exercise:
//
// Tiles test recognition, and recall is what builds durable memory - so this
// never replaces the SRS ladder (cloze → dictation → recall). It replaces the
// PRETEST, the "guess it, even wildly" prompt, and only for learners who have
// nothing to guess from yet. Scaffolding helps novices and stops helping once
// they are competent (the expertise reversal effect), so SentenceCard fades it
// out automatically - see BEGINNER_MASTERED_MAX there.
//
// The word set is graded, not the word order: Finnish marks roles with case
// endings, so "Kahvin mä otan" is as correct as "Mä otan kahvin" - only the
// emphasis moves. Failing a learner for real Finnish would teach a rule the
// language doesn't have, so a reordering is accepted and the everyday order is
// shown alongside it.
import { computed, ref, watch } from 'vue'
import { Check, Ear, RotateCcw, Volume2 } from 'lucide-vue-next'
import { assemblyVerdict, sentenceWords } from '../utils/practice'
import { useFinnishAudio } from '../composables/useFinnishAudio'

const props = defineProps({
  text: { type: String, required: true },
  english: { type: String, default: '' },
  audioUrl: { type: String, default: null },
  // Words from the rest of today's session - plausible distractors, because
  // they are the learner's own material rather than random noise.
  pool: { type: Array, default: () => [] }
})

const emit = defineEmits(['solved'])

const { playSentence } = useFinnishAudio()

// Enough choice to require reading, few enough to stay tappable one-handed.
const MAX_DISTRACTORS = 3

const tokens = computed(() => props.text.split(/\s+/).filter(Boolean))

const tiles = ref([]) // [{ id, word, decoy }]
const placedIds = ref([])
const verdict = ref(null) // null | 'perfect' | 'reordered' | 'wrong' | 'shown'
// A learner who cannot solve the puzzle must never be trapped in it: one wrong
// attempt unlocks the way out, and being shown the answer is a normal outcome
// at this stage, not a failure.
const attempts = ref(0)

const placed = computed(() => placedIds.value.map((id) => tiles.value.find((t) => t.id === id)).filter(Boolean))
const bank = computed(() => tiles.value.filter((t) => !placedIds.value.includes(t.id)))
const solved = computed(() => ['perfect', 'reordered', 'shown'].includes(verdict.value))

function shuffled(list) {
  const out = [...list]
  for (let i = out.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1))
    ;[out[i], out[j]] = [out[j], out[i]]
  }
  return out
}

function setup() {
  const own = sentenceWords(props.text)
  // Distractors the learner might plausibly confuse: from today's other
  // sentences, never a word this sentence already uses.
  const decoys = shuffled(
    [...new Set(props.pool.map((w) => w.trim()).filter(Boolean))].filter((w) => {
      const parts = sentenceWords(w)
      return parts.length === 1 && !own.includes(parts[0])
    })
  ).slice(0, MAX_DISTRACTORS)

  let next = shuffled([
    ...tokens.value.map((word, i) => ({ id: `w${i}`, word, decoy: false })),
    ...decoys.map((word, i) => ({ id: `d${i}`, word, decoy: true }))
  ])

  // A bank that happens to already read in order gives the answer away.
  if (next.filter((t) => !t.decoy).map((t) => t.word).join(' ') === tokens.value.join(' ')) {
    next = next.reverse()
  }

  tiles.value = next
  placedIds.value = []
  verdict.value = null
  attempts.value = 0
  play()
}

watch(() => props.text, setup, { immediate: true })

function play(rate = null) {
  playSentence(props.text, props.audioUrl, rate)
}

function place(tile) {
  if (solved.value) return
  placedIds.value.push(tile.id)
  verdict.value = null
}

function unplace(tile) {
  if (solved.value) return
  placedIds.value = placedIds.value.filter((id) => id !== tile.id)
  verdict.value = null
}

function clear() {
  if (solved.value) return
  placedIds.value = []
  verdict.value = null
}

function check() {
  if (!placedIds.value.length || solved.value) return

  verdict.value = assemblyVerdict(
    placed.value.map((t) => t.word),
    tokens.value
  )

  if (verdict.value === 'wrong') {
    attempts.value++

    return
  }

  play()
  emit('solved', verdict.value)
}

function showAnswer() {
  verdict.value = 'shown'
  play()
  emit('solved', 'shown')
}
</script>

<template>
  <div class="builder">
    <p class="prompt-en">{{ english }}</p>

    <div class="listen-row">
      <button class="listen" @click="play()"><Volume2 class="listen-ico" aria-hidden="true" /> Listen</button>
      <button class="listen slow" title="Slowly" @click="play(0.6)"><Ear class="listen-ico" aria-hidden="true" /> Slowly</button>
    </div>

    <!-- the assembly line -->
    <div class="line" :class="{ empty: !placed.length, [verdict ?? '']: !!verdict }">
      <button
        v-for="t in placed"
        :key="t.id"
        class="tile placed"
        :disabled="solved"
        @click="unplace(t)"
      >{{ t.word }}</button>
      <span v-if="!placed.length" class="line-hint">Tap the words in order ↓</span>
    </div>

    <!-- the word bank -->
    <div class="bank">
      <button
        v-for="t in bank"
        :key="t.id"
        class="tile"
        :disabled="solved"
        @click="place(t)"
      >{{ t.word }}</button>
    </div>

    <p v-if="verdict === 'wrong'" class="verdict wrong">
      Not quite - listen once more and check the words you picked.
    </p>
    <p v-else-if="verdict === 'reordered'" class="verdict ok">
      That works! Finnish lets you move words around - the endings carry the meaning, so
      only the emphasis shifts. The order you'll hear most: <b>{{ text }}</b>
    </p>
    <p v-else-if="verdict === 'perfect'" class="verdict ok">Täydellistä! That's exactly it.</p>
    <p v-else-if="verdict === 'shown'" class="verdict muted">
      Here it is: <b>{{ text }}</b> - you'll build this one again soon.
    </p>

    <div v-if="!solved" class="actions">
      <button class="btn btn-ghost clear-btn" :disabled="!placed.length" @click="clear">
        <RotateCcw class="btn-ico" aria-hidden="true" /> Clear
      </button>
      <button class="btn btn-primary check-btn" :disabled="!placed.length" @click="check">
        <Check class="btn-ico" aria-hidden="true" /> Check
      </button>
    </div>
    <button v-if="!solved && attempts" class="show-me" @click="showAnswer">Show me the answer</button>
  </div>
</template>

<style scoped>
.builder { display: flex; flex-direction: column; gap: 14px; }

.prompt-en { font-size: 20px; font-weight: 700; line-height: 1.35; }

.listen-row { display: flex; gap: 8px; }
.listen {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: var(--accent-soft);
  color: var(--accent);
  border: none;
  border-radius: var(--radius-pill);
  padding: 9px 16px;
  font-family: inherit;
  font-size: 13.5px;
  font-weight: 700;
  cursor: pointer;
}
.listen.slow { background: var(--bg-soft); color: var(--text-dim); }
.listen-ico { width: 15px; height: 15px; flex-shrink: 0; }

.line {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  min-height: 62px;
  padding: 12px;
  border: 2px dashed var(--border);
  border-radius: var(--radius-sm);
  transition: border-color 0.15s ease, background 0.15s ease;
}
.line.perfect, .line.reordered { border-style: solid; border-color: var(--green); background: var(--green-soft); }
.line.wrong { border-style: solid; border-color: var(--red, #f87171); }
.line-hint { color: var(--text-dim); font-size: 13.5px; font-weight: 600; }

.bank { display: flex; flex-wrap: wrap; gap: 8px; min-height: 44px; }

.tile {
  background: var(--card);
  border: 1px solid var(--border);
  border-bottom-width: 3px;
  border-radius: 10px;
  padding: 10px 14px;
  font-family: inherit;
  font-size: 17px;
  font-weight: 700;
  color: var(--text);
  cursor: pointer;
  transition: transform 0.08s ease, border-color 0.15s ease;
}
.tile:hover:not(:disabled) { border-color: var(--accent); }
.tile:active:not(:disabled) { transform: translateY(1px); }
.tile:disabled { cursor: default; opacity: 0.9; }
.tile.placed { background: var(--accent-soft); border-color: var(--accent); color: var(--text); }

.verdict { font-size: 13.5px; line-height: 1.5; }
.verdict.ok { color: var(--green); }
.verdict.ok b, .verdict.muted b { color: var(--text); }
.verdict.wrong { color: var(--red, #f87171); }

.show-me {
  align-self: center;
  background: none;
  border: none;
  font-family: inherit;
  font-size: 13px;
  font-weight: 600;
  color: var(--text-dim);
  text-decoration: underline;
  text-underline-offset: 2px;
  cursor: pointer;
  padding: 2px 6px;
}
.show-me:hover { color: var(--text); }

.actions { display: flex; gap: 10px; }
.clear-btn { flex: 0 0 auto; }
.check-btn { flex: 1; }
.btn-ico { width: 15px; height: 15px; vertical-align: -3px; margin-right: 5px; }
</style>
