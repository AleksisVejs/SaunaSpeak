<script setup>
import { onBeforeUnmount, ref, watch } from 'vue'
import api from '../api'
import { typoDistance } from '../utils/practice'

const props = defineProps({
  expected: { type: String, required: true },
  // English meaning of the expected sentence - anchors the AI correction
  // to the exercise instead of whatever the attempt happened to resemble.
  translation: { type: String, default: '' },
  placeholder: { type: String, default: '' }
})

const emit = defineEmits(['checked'])

const attempt = ref('')
const listening = ref(false)
const checking = ref(false)
const feedback = ref(null) // { correct, corrected, explanation, tokens, accentsOnly }

const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition
const micSupported = !!SpeechRecognition
let recognition = null

watch(
  () => props.expected,
  () => {
    attempt.value = ''
    feedback.value = null
    stopListening()
  }
)

function normalize(s) {
  return s
    .toLowerCase()
    .replace(/[^\p{L}\p{N} ]/gu, '')
    .replace(/\s+/g, ' ')
    .trim()
}

// Diacritic-insensitive form: ä→a, ö→o, å→a. Lets us spot "accents-only" misses.
function stripDiacritics(s) {
  return normalize(s).normalize('NFD').replace(/\p{Diacritic}/gu, '')
}

function normWord(w) {
  return w.toLowerCase().replace(/[^\p{L}\p{N}]/gu, '')
}

// Highlight which words of the correct answer the learner didn't get right.
function diffTokens(correct, userAttempt) {
  const userSet = new Set(normalize(userAttempt).split(' ').filter(Boolean))
  return correct.split(/(\s+)/).map((tok) => {
    if (!tok.trim()) return { text: tok, changed: false }
    return { text: tok, changed: !userSet.has(normWord(tok)) }
  })
}

function startListening() {
  if (!micSupported || listening.value) return
  recognition = new SpeechRecognition()
  recognition.lang = 'fi-FI'
  recognition.interimResults = false
  recognition.maxAlternatives = 1
  recognition.onresult = (e) => {
    attempt.value = e.results[0][0].transcript
    check()
  }
  recognition.onend = () => (listening.value = false)
  recognition.onerror = () => (listening.value = false)
  listening.value = true
  recognition.start()
}

function stopListening() {
  recognition?.abort()
  listening.value = false
}

async function check() {
  if (!attempt.value.trim() || checking.value) return
  checking.value = true

  if (normalize(attempt.value) === normalize(props.expected)) {
    feedback.value = {
      correct: true,
      corrected: props.expected,
      explanation: 'Täydellistä! Exactly how a Finn says it. 🔥',
      tokens: null,
      accentsOnly: false
    }
  } else if (
    // Missing ä/ö dots are meaningful in Finnish, never "just a typo" - so the
    // accent check runs first and gets its own kinder callout below.
    stripDiacritics(attempt.value) !== stripDiacritics(props.expected) &&
    typoDistance(normalize(attempt.value), normalize(props.expected)) !== null
  ) {
    // A letter missing, doubled or swapped: that's a slip of the finger, not
    // a language mistake - count it as correct locally instead of spending
    // an AI call on it. Show the exact form so the typo still gets seen.
    feedback.value = {
      correct: true,
      corrected: props.expected,
      explanation: 'Right! Just a tiny typo - the exact spelling is above. 👌',
      tokens: null,
      accentsOnly: false
    }
  } else {
    // Right words, wrong accents (ä/ö) - worth calling out kindly.
    const accentsOnly = stripDiacritics(attempt.value) === stripDiacritics(props.expected)
    let corrected = props.expected
    let explanation = accentsOnly
      ? 'So close - same words, just the dots on ä/ö. They change the sound (and meaning), so they matter!'
      : 'Compare your version with the expected sentence.'

    if (!accentsOnly) {
      try {
        const { data } = await api.post('/ai/correct', {
          user_sentence: attempt.value,
          expected_sentence: props.expected,
          expected_translation: props.translation || undefined
        })
        corrected = data.corrected
        explanation = data.explanation
      } catch {
        // keep the fallback explanation
      }
    }

    feedback.value = {
      correct: false,
      corrected,
      explanation,
      tokens: diffTokens(corrected, attempt.value),
      accentsOnly
    }
  }

  checking.value = false
  emit('checked', feedback.value.correct)
}

onBeforeUnmount(stopListening)
</script>

<template>
  <div class="practice">
    <div class="input-row">
      <button
        v-if="micSupported"
        class="btn btn-ghost mic-btn"
        :class="{ listening }"
        :title="listening ? 'Listening… tap to stop' : 'Say it in Finnish'"
        @click="listening ? stopListening() : startListening()"
      >
        {{ listening ? '👂' : '🎤' }}
      </button>
      <input
        v-model="attempt"
        type="text"
        class="attempt-input"
        :placeholder="listening ? 'Listening…' : placeholder || (micSupported ? 'Speak 🎤 or type in Finnish' : 'Type it in Finnish')"
        autocapitalize="none"
        autocomplete="off"
        spellcheck="false"
        @keyup.enter="check"
      />
      <button class="btn btn-ghost check-btn" :disabled="!attempt.trim() || checking" @click="check">
        {{ checking ? '…' : 'Check' }}
      </button>
    </div>

    <transition name="fade">
      <div v-if="feedback" class="feedback" :class="feedback.correct ? 'good' : (feedback.accentsOnly ? 'accents' : 'close')">
        <p class="corrected">
          <span class="mark">{{ feedback.correct ? '✓' : (feedback.accentsOnly ? '＾' : '✏️') }}</span>
          <template v-if="feedback.tokens">
            <span
              v-for="(t, i) in feedback.tokens"
              :key="i"
              :class="{ changed: t.changed }"
            >{{ t.text }}</span>
          </template>
          <template v-else>{{ feedback.corrected }}</template>
        </p>
        <p class="explanation">{{ feedback.explanation }}</p>
      </div>
    </transition>
  </div>
</template>

<style scoped>
.practice { display: flex; flex-direction: column; gap: 10px; }
.input-row { display: flex; gap: 8px; }
.mic-btn { padding: 12px 14px; font-size: 17px; flex-shrink: 0; }
.mic-btn.listening {
  border-color: var(--accent);
  color: var(--accent);
  animation: mic-pulse 1.2s ease-in-out infinite;
}
@keyframes mic-pulse {
  0%, 100% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.35); }
  50% { box-shadow: 0 0 0 8px rgba(245, 158, 11, 0); }
}
.attempt-input {
  flex: 1;
  min-width: 0;
  background: var(--bg-soft);
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  padding: 12px 14px;
  font-size: 16px;
  font-family: inherit;
  color: var(--text);
  outline: none;
  transition: border-color 0.15s ease;
}
.attempt-input:focus { border-color: var(--accent); }
.check-btn { padding: 12px 16px; font-size: 14px; flex-shrink: 0; }
.feedback {
  border-radius: var(--radius-sm);
  padding: 12px 14px;
  border: 1px solid;
}
.feedback.good { background: var(--green-soft); border-color: rgba(52, 211, 153, 0.35); }
.feedback.close { background: var(--accent-soft); border-color: rgba(245, 158, 11, 0.35); }
.feedback.accents { background: var(--blue-soft); border-color: rgba(96, 165, 250, 0.35); }
.corrected { font-weight: 700; font-size: 16px; }
.corrected .mark { margin-right: 4px; }
.corrected .changed {
  color: var(--accent);
  background: var(--accent-soft);
  border-radius: 4px;
  padding: 0 2px;
  text-decoration: underline;
  text-decoration-style: wavy;
  text-underline-offset: 3px;
}
.explanation { color: var(--text-dim); font-size: 14px; margin-top: 4px; line-height: 1.4; }
</style>
