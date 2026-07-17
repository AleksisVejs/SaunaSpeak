<script setup>
// In-session USE step: the output beat that closes the session. Recall and
// recognition aren't production - so here the learner GENERATES a sentence
// from today out loud, from the English alone, checked by the same AI corrector
// the cards use (the output hypothesis: producing is what exposes the gaps).
//
// When the backend matched a roleplay scenario (premium), it rides along as a
// non-blocking "take it further" link - the produce beat is what completes the
// session, so nobody is pulled off to another page to finish.
import { ref } from 'vue'
import { ArrowRight, Drama, Eye, Sparkles, Volume2 } from 'lucide-vue-next'
import PracticeInput from './PracticeInput.vue'
import { useFinnishAudio } from '../composables/useFinnishAudio'

const props = defineProps({
  data: { type: Object, required: true } // { sentence, scenario|null }
})
const emit = defineEmits(['done'])

const { playSentence } = useFinnishAudio()
const revealed = ref(false)
const sentence = props.data.sentence

function onChecked() {
  revealed.value = true
  playSentence(sentence.finnish_text, sentence.audio_url)
}
function reveal() {
  revealed.value = true
  playSentence(sentence.finnish_text, sentence.audio_url)
}
</script>

<template>
  <div class="use-step">
    <div class="step-head">
      <span class="step-kicker"><Sparkles class="kicker-ico" aria-hidden="true" /> Use it</span>
      <p class="step-title">Say it for real</p>
      <p class="step-why muted">No word bank, no cloze - produce the whole thing from the English. This is the part that turns "I recognize it" into "I can say it".</p>
    </div>

    <div class="card prompt-card">
      <p class="en">{{ sentence.english_text }}</p>
      <p v-if="revealed" class="fi">
        <button class="say" :title="'Hear ' + sentence.finnish_text" @click="playSentence(sentence.finnish_text, sentence.audio_url)">
          <Volume2 class="say-ico" aria-hidden="true" />
        </button>
        {{ sentence.finnish_text }}
      </p>
    </div>

    <PracticeInput
      :expected="sentence.finnish_text"
      :translation="sentence.english_text"
      :written="sentence.written_text || ''"
      placeholder="Say or type it in Finnish"
      @checked="onChecked"
      @confirm="revealed = true"
    />

    <!-- non-blocking: extend into a full roleplay if one matched (premium) -->
    <router-link
      v-if="data.scenario"
      :to="`/chat?scenario=${data.scenario.id}`"
      class="card scenario-cta"
    >
      <Drama class="cta-ico" aria-hidden="true" />
      <span class="cta-text">
        <span class="cta-title">Take it further: {{ data.scenario.emoji }} {{ data.scenario.title }}</span>
        <span class="cta-sub muted">{{ data.scenario.mission }}</span>
      </span>
      <ArrowRight class="cta-arrow" aria-hidden="true" />
    </router-link>

    <div class="actions">
      <button v-if="!revealed" class="btn btn-ghost btn-block" @click="reveal">
        <Eye class="btn-ico" aria-hidden="true" /> Show me
      </button>
      <button v-else class="btn btn-primary btn-block" @click="emit('done', 0)">
        Finish session <ArrowRight class="btn-ico-r" aria-hidden="true" />
      </button>
    </div>
  </div>
</template>

<style scoped>
.use-step { display: flex; flex-direction: column; gap: 14px; flex: 1; }

.step-head { display: flex; flex-direction: column; gap: 3px; }
.step-kicker {
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: var(--accent);
  display: inline-flex;
  align-items: center;
  gap: 5px;
}
.kicker-ico { width: 13px; height: 13px; }
.step-title { font-size: 19px; font-weight: 800; }
.step-why { font-size: 12.5px; line-height: 1.45; margin-top: 4px; }

.prompt-card { display: flex; flex-direction: column; gap: 8px; }
.en { font-size: 18px; font-weight: 700; line-height: 1.4; }
.fi { font-size: 16px; font-weight: 800; color: var(--accent); line-height: 1.4; }
.say { background: none; border: none; padding: 0 4px 0 0; color: var(--accent); cursor: pointer; vertical-align: -2px; }
.say-ico { width: 15px; height: 15px; display: inline-block; }

.scenario-cta {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 14px;
  border: 1px dashed var(--accent);
  background: var(--accent-soft);
  color: var(--text);
}
.cta-ico { width: 20px; height: 20px; color: var(--accent); flex-shrink: 0; }
.cta-text { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
.cta-title { font-weight: 800; font-size: 14px; }
.cta-sub { font-size: 12px; line-height: 1.35; }
.cta-arrow { width: 16px; height: 16px; color: var(--accent); flex-shrink: 0; }

.actions { margin-top: auto; }
.btn-ico { width: 16px; height: 16px; vertical-align: -3px; margin-right: 5px; }
.btn-ico-r { width: 16px; height: 16px; vertical-align: -3px; margin-left: 3px; }
</style>
