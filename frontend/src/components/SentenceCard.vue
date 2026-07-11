<script setup>
import { computed, nextTick, ref, watch } from 'vue'
import AudioButton from './AudioButton.vue'
import ShadowCompare from './ShadowCompare.vue'
import TappableSentence from './TappableSentence.vue'
import { cardKind, clozeText } from '../utils/practice'

const props = defineProps({
  sentence: { type: Object, required: true },
  status: { type: String, default: null },
  // 'study' (session): exercise varies with SRS stage - see cardKind().
  // 'browse' (lesson list): everything visible except the translation.
  mode: { type: String, default: 'browse' }
})

const emit = defineEmits(['revealed'])

const revealed = ref(false)
const guessed = ref(false)
const audio = ref(null)

// study = guess, then listen & shadow; cloze = fill the gap; dictation = ears only; recall = English → Finnish
const kind = computed(() => (props.mode === 'study' ? cardKind(props.status) : 'browse'))
const quiz = computed(() => ['cloze', 'dictation', 'recall'].includes(kind.value))

// Pretesting: attempting to retrieve before studying strengthens the memory,
// even when the guess is wrong - so new sentences show the English first.
const pretest = computed(() => kind.value === 'study' && !guessed.value)

const kindLabels = {
  study: '✨ New - listen & shadow',
  pretest: '🤔 New - guess it first, even wildly',
  cloze: '🧩 Fill the gap - catch the missing word',
  dictation: '✍️ Dictation - what do you hear?',
  recall: '🧠 Recall - say it in Finnish, out loud'
}

const hintLabel = computed(() => kindLabels[pretest.value ? 'pretest' : kind.value])

const clozed = computed(() => clozeText(props.sentence.finnish_text))

watch(
  () => props.sentence.id,
  async () => {
    revealed.value = false
    guessed.value = false
    // Listen-first for ear-driven quizzes. New sentences stay silent until
    // the guess - hearing the answer would defeat the pretest.
    if (['cloze', 'dictation'].includes(kind.value)) {
      await nextTick()
      audio.value?.play()
    }
  },
  { immediate: true }
)

async function reveal() {
  // New sentence: the guess is over - show the Finnish and hear it.
  if (kind.value === 'study') {
    if (guessed.value) return
    guessed.value = true
    emit('revealed')
    await nextTick()
    audio.value?.play()
    return
  }

  if (revealed.value) return
  revealed.value = true
  emit('revealed')
  if (quiz.value) {
    // Confirmation listening: hear it again while reading the answer.
    await nextTick()
    audio.value?.play()
  }
}

defineExpose({ reveal })

const statusLabels = {
  new: 'New',
  learning: 'Learning',
  review: 'Review',
  mastered: 'Mastered'
}
</script>

<template>
  <div class="card sentence-card">
    <span v-if="status" class="status-pill" :class="`status-${status}`">
      {{ statusLabels[status] ?? status }}
    </span>

    <p v-if="kind !== 'browse'" class="hint">{{ hintLabel }}</p>
    <p v-else-if="sentence.speaker" class="hint">🧖 Speaker {{ sentence.speaker }}</p>

    <!-- Dialogue context: the line this sentence replies to -->
    <p v-if="sentence.context_text" class="context">💬 "{{ sentence.context_text }}"</p>

    <!-- Pretest: English shown, learner attempts the Finnish before seeing it -->
    <template v-if="pretest">
      <img v-if="sentence.image_url" :src="sentence.image_url" class="sentence-img" alt="" />
      <p class="finnish">{{ sentence.english_text }}</p>
      <p class="pretest-nudge">A wrong guess still primes your memory - say something!</p>
      <button class="btn btn-ghost reveal-btn" @click="reveal">
        👁 Show the Finnish
      </button>
    </template>

    <!-- Quiz stage: something is hidden until the learner attempts it -->
    <template v-else-if="quiz && !revealed">
      <p v-if="kind === 'recall'" class="finnish">{{ sentence.english_text }}</p>

      <template v-else-if="kind === 'cloze'">
        <p class="finnish">{{ clozed }}</p>
        <AudioButton ref="audio" :text="sentence.finnish_text" :audio-url="sentence.audio_url" />
      </template>

      <template v-else>
        <p class="dictation-icon">🎧</p>
        <AudioButton ref="audio" :text="sentence.finnish_text" :audio-url="sentence.audio_url" />
      </template>

      <button class="btn btn-ghost reveal-btn" @click="reveal">
        👁 {{ kind === 'recall' ? 'Show the Finnish' : 'Show the answer' }}
      </button>
    </template>

    <!-- Full card: new sentences, lesson browsing, or a revealed quiz -->
    <template v-else>
      <!-- Dual coding: picture + text + sound beats text alone for retention -->
      <img v-if="sentence.image_url && !quiz" :src="sentence.image_url" class="sentence-img" alt="" />
      <TappableSentence
        class="finnish"
        :text="sentence.finnish_text"
        :glosses="sentence.word_glosses"
        :sentence-id="sentence.id"
      />

      <p v-if="sentence.written_text" class="written" title="Kirjakieli - how it's written in books and news">
        📖 {{ sentence.written_text }}
      </p>

      <AudioButton ref="audio" :text="sentence.finnish_text" :audio-url="sentence.audio_url" />

      <template v-if="kind === 'study'">
        <p class="hint">🗣 Listen, then say it out loud - twice</p>
        <ShadowCompare :text="sentence.finnish_text" :audio-url="sentence.audio_url" />
      </template>

      <!-- Quizzes and guessed pretests already showed the English - keep it visible. -->
      <p v-if="quiz || kind === 'study'" class="english">{{ sentence.english_text }}</p>

      <div v-else class="translation-zone">
        <transition name="fade" mode="out-in">
          <p v-if="revealed" class="english">{{ sentence.english_text }}</p>
          <button v-else class="btn btn-ghost reveal-btn" @click="revealed = true">
            👁 Show translation
          </button>
        </transition>
      </div>
    </template>
  </div>
</template>

<style scoped>
.sentence-card {
  display: flex;
  flex-direction: column;
  gap: 18px;
  position: relative;
}
.status-pill {
  position: absolute;
  top: 14px;
  right: 14px;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  padding: 4px 10px;
  border-radius: 99px;
  background: var(--bg-soft);
  color: var(--text-dim);
}
.status-new { background: var(--accent-soft); color: var(--accent); }
.status-mastered { background: var(--green-soft); color: var(--green); }
.finnish {
  font-size: 26px;
  font-weight: 700;
  line-height: 1.35;
}
/* keep the sentence text clear of the status pill, but let the gloss panel span full width */
p.finnish,
.finnish :deep(.sentence) { padding-right: 70px; }
.dictation-icon { font-size: 42px; text-align: center; padding: 8px 0; }
.context {
  color: var(--text-dim);
  font-size: 15px;
  font-style: italic;
  padding-right: 70px;
}
.written {
  color: var(--text-dim);
  font-size: 14px;
  margin-top: -10px;
  cursor: help;
}
.hint {
  color: var(--text-dim);
  font-size: 13px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  padding-right: 70px;
}
.sentence-img {
  width: 68px;
  height: 68px;
  align-self: center;
  margin-bottom: -6px;
  user-select: none;
  -webkit-user-drag: none;
}
.pretest-nudge { color: var(--text-dim); font-size: 14px; font-style: italic; }
.translation-zone { min-height: 48px; display: flex; align-items: center; }
.english { color: var(--text-dim); font-size: 17px; line-height: 1.4; }
.reveal-btn { padding: 10px 16px; font-size: 14px; color: var(--text-dim); align-self: flex-start; }
</style>
