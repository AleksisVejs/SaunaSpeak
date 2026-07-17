<script setup>
// In-session BEND step: a Taivutus set woven in after listening. Finnish keeps
// its meaning in the endings, so somewhere the learner has to GENERATE a form,
// not just recall a whole sentence - this is that beat, moved into the daily
// loop instead of hidden on a side page.
//
// Emits 'done' (with any XP the completion endpoint awarded) to advance.
import { computed, onMounted, ref } from 'vue'
import { ArrowRight, Eye, Lightbulb, Volume2, Wrench } from 'lucide-vue-next'
import api from '../api'
import { useAuthStore } from '../stores/auth'
import { useFinnishAudio } from '../composables/useFinnishAudio'
import PracticeInput from './PracticeInput.vue'

const props = defineProps({
  data: { type: Object, required: true } // { id, emoji, title, rule }
})
const emit = defineEmits(['done'])

const auth = useAuthStore()
const { playSentence } = useFinnishAudio()

const set = ref(null)
const loading = ref(true)
const index = ref(0)
const answered = ref(false)

onMounted(async () => {
  try {
    const { data } = await api.get(`/transforms/${props.data.id}`)
    set.value = data.set
  } finally {
    loading.value = false
  }
})

const items = computed(() => set.value?.items ?? [])
const currentItem = computed(() => items.value[index.value] ?? null)
const total = computed(() => items.value.length)

function playTo() {
  if (currentItem.value?.to_audio) playSentence(currentItem.value.to, currentItem.value.to_audio)
}
function playFrom() {
  if (currentItem.value?.from_audio) playSentence(currentItem.value.from, currentItem.value.from_audio)
}

function onChecked() {
  answered.value = true
  playTo()
}
function reveal() {
  answered.value = true
  playTo()
}

async function next() {
  if (index.value + 1 >= total.value) return finishStep()
  index.value++
  answered.value = false
}

// Cleared the set: first clear pays XP. Then advance the session.
async function finishStep() {
  let xp = 0
  try {
    const { data } = await api.post(`/transforms/${props.data.id}/complete`)
    xp = data.xp_gained
    auth.fetchUser()
  } catch {
    // The practice happened either way - only the badge is at risk.
  }
  emit('done', xp)
}
</script>

<template>
  <div class="transform-step">
    <div class="step-head">
      <span class="step-kicker"><Wrench class="kicker-ico" aria-hidden="true" /> Bend it</span>
      <p class="step-title">{{ data.emoji }} {{ data.title }}</p>
    </div>

    <div v-if="loading" class="spinner"></div>

    <template v-else-if="currentItem">
      <div class="mini-top">
        <div class="progress-track"><div class="progress-fill" :style="{ width: Math.round((index / total) * 100) + '%' }"></div></div>
        <span class="counter">{{ index + 1 }}/{{ total }}</span>
      </div>

      <p class="rule-strip"><Wrench class="rule-ico" aria-hidden="true" /> {{ set.rule }}</p>

      <div class="card item">
        <p class="prompt">{{ currentItem.prompt }}</p>
        <div class="from-row">
          <div class="from">
            <p class="from-fi">
              <button v-if="currentItem.from_audio" class="say" :title="'Hear ' + currentItem.from" @click="playFrom">
                <Volume2 class="say-ico" aria-hidden="true" />
              </button>
              {{ currentItem.from }}
            </p>
            <p class="from-en muted">{{ currentItem.from_en }}</p>
          </div>
          <ArrowRight class="arrow" aria-hidden="true" />
          <div class="to">
            <p v-if="answered" class="to-fi">
              <button v-if="currentItem.to_audio" class="say accent" :title="'Hear ' + currentItem.to" @click="playTo">
                <Volume2 class="say-ico" aria-hidden="true" />
              </button>
              {{ currentItem.to }}
            </p>
            <p v-else class="to-blank">?</p>
            <p v-if="answered" class="to-en muted">{{ currentItem.to_en }}</p>
          </div>
        </div>
        <transition name="fade">
          <p v-if="answered" class="note"><Lightbulb class="note-ico" aria-hidden="true" /> {{ currentItem.note }}</p>
        </transition>
      </div>

      <PracticeInput
        :key="`ts-${index}`"
        :expected="currentItem.to"
        :translation="currentItem.to_en"
        :accepts="currentItem.accepts || []"
        :rejects="[currentItem.from]"
        placeholder="Say or type the changed sentence"
        @checked="onChecked"
        @confirm="next"
      />

      <div class="actions">
        <button v-if="!answered" class="btn btn-ghost btn-block" @click="reveal">
          <Eye class="btn-ico" aria-hidden="true" /> Show me
        </button>
        <button v-else class="btn btn-primary btn-block" @click="next">
          {{ index + 1 >= total ? 'Continue' : 'Next' }} <ArrowRight class="btn-ico-r" aria-hidden="true" />
        </button>
      </div>
    </template>
  </div>
</template>

<style scoped>
.transform-step { display: flex; flex-direction: column; gap: 14px; flex: 1; }

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

.mini-top { display: flex; align-items: center; gap: 12px; }
.mini-top .progress-track { flex: 1; }
.counter { font-size: 13px; font-weight: 700; color: var(--text-dim); }

.rule-strip {
  font-size: 12.5px;
  font-weight: 700;
  color: var(--accent);
  background: var(--accent-soft);
  border-radius: var(--radius-pill);
  padding: 7px 13px;
  align-self: flex-start;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.rule-ico { width: 13px; height: 13px; flex-shrink: 0; }

.item { display: flex; flex-direction: column; gap: 14px; }
.prompt {
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: var(--text-dim);
}
.from-row { display: flex; align-items: center; gap: 12px; }
.from, .to { flex: 1; min-width: 0; }
.from-fi { font-size: 17px; font-weight: 700; line-height: 1.35; }
.say { background: none; border: none; padding: 0 4px 0 0; color: var(--text-faint); cursor: pointer; vertical-align: -2px; }
.say:hover { color: var(--accent); }
.say.accent { color: var(--accent); }
.say-ico { width: 15px; height: 15px; display: inline-block; }
.from-en { font-size: 12.5px; margin-top: 3px; line-height: 1.35; }
.arrow { width: 20px; height: 20px; color: var(--accent); flex-shrink: 0; }
.to-fi { font-size: 17px; font-weight: 800; color: var(--accent); line-height: 1.35; }
.to-en { font-size: 12.5px; margin-top: 3px; line-height: 1.35; }
.to-blank { font-size: 22px; font-weight: 800; color: var(--text-faint); }
@media (max-width: 480px) {
  .from-row { flex-direction: column; align-items: stretch; gap: 8px; }
  .arrow { transform: rotate(90deg); align-self: center; }
}

.note { font-size: 13px; line-height: 1.5; color: var(--text-dim); background: var(--bg-soft); border-radius: var(--radius-sm); padding: 11px 13px; }
.note-ico { width: 14px; height: 14px; vertical-align: -2px; margin-right: 4px; color: var(--accent); }

.actions { margin-top: auto; }
.btn-ico { width: 16px; height: 16px; vertical-align: -3px; margin-right: 5px; }
.btn-ico-r { width: 16px; height: 16px; vertical-align: -3px; margin-left: 3px; }
</style>
