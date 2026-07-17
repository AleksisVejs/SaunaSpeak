<script setup>
// In-session LISTEN step: a whole conversation, woven into the daily session
// right after the sentence block. Same scene the Kuuntelu library plays, in a
// compact shape - the point is that following the guided path now trains
// comprehension (volume of connected speech), not just recall.
//
// Emits 'done' (with any XP its completion endpoint awarded) to advance the
// session. The learner can continue whenever they like - listening isn't a
// test to pass.
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { ArrowRight, Eye, EyeOff, Headphones, Pause, Play, Turtle } from 'lucide-vue-next'
import api from '../api'
import { useAuthStore } from '../stores/auth'
import { useFinnishAudio } from '../composables/useFinnishAudio'

const props = defineProps({
  data: { type: Object, required: true } // { id, emoji, title, tagline, done }
})
const emit = defineEmits(['done'])

const auth = useAuthStore()
const { playSentence, playSentenceAsync, stop } = useFinnishAudio()

const SLOW_RATE = 0.7

const scene = ref(null)
const loading = ref(true)
const playing = ref(false)
const current = ref(-1)
const showAllFi = ref(false)
const heardOnce = ref(false)
const completed = ref(false)
let runToken = 0

onMounted(async () => {
  try {
    const { data } = await api.get(`/listening/${props.data.id}`)
    scene.value = data.scene
    completed.value = data.scene.done
  } finally {
    loading.value = false
  }
})

onUnmounted(() => {
  runToken++
  stop()
})

const lines = computed(() => scene.value?.lines ?? [])
const hasAudio = computed(() => lines.value.some((l) => l.audio_url))

function speakerOf(line) {
  return scene.value?.speakers?.[line.who] ?? { name: line.who, voice: 'male' }
}

async function playAll(rate = null) {
  if (playing.value) return stopPlayback()
  const token = ++runToken
  playing.value = true
  for (let i = 0; i < lines.value.length; i++) {
    if (token !== runToken) return
    current.value = i
    await playSentenceAsync(lines.value[i].fi, lines.value[i].audio_url, rate)
    if (token !== runToken) return
    await new Promise((r) => setTimeout(r, 320))
  }
  if (token !== runToken) return
  playing.value = false
  current.value = -1
  heardOnce.value = true
}

function stopPlayback() {
  runToken++
  stop()
  playing.value = false
  current.value = -1
}

function playLine(i, rate = null) {
  stopPlayback()
  current.value = i
  playSentence(lines.value[i].fi, lines.value[i].audio_url, rate)
}

// Mark it heard (first time pays XP), then advance the session.
async function finishStep() {
  stopPlayback()
  let xp = 0
  if (!completed.value) {
    try {
      const { data } = await api.post(`/listening/${props.data.id}/complete`)
      xp = data.xp_gained
      completed.value = true
      auth.fetchUser()
    } catch {
      // Listening still happened - only the badge is at risk.
    }
  }
  emit('done', xp)
}
</script>

<template>
  <div class="listen-step">
    <div class="step-head">
      <span class="step-kicker"><Headphones class="kicker-ico" aria-hidden="true" /> Listen</span>
      <p class="step-title">{{ data.emoji }} {{ data.title }}</p>
      <p class="step-sub muted">{{ data.tagline }}</p>
      <p class="step-why muted">Two Finns, natural speed. Try it with the text hidden - understanding without reading is the skill.</p>
    </div>

    <div v-if="loading" class="spinner"></div>

    <template v-else-if="scene">
      <div v-if="!hasAudio" class="card no-audio">
        <p class="muted">This scene has no audio yet.</p>
      </div>

      <template v-else>
        <div class="controls">
          <button class="btn btn-primary play-btn" @click="playAll()">
            <component :is="playing ? Pause : Play" class="ctl-ico" aria-hidden="true" />
            {{ playing ? 'Stop' : 'Play conversation' }}
          </button>
          <button class="btn btn-ghost ctl-btn" title="Play slowly" @click="playAll(SLOW_RATE)">
            <Turtle class="ctl-ico" aria-hidden="true" /> Slow
          </button>
          <button class="btn btn-ghost ctl-btn" @click="showAllFi = !showAllFi">
            <component :is="showAllFi ? EyeOff : Eye" class="ctl-ico" aria-hidden="true" />
            {{ showAllFi ? 'Hide text' : 'Show text' }}
          </button>
        </div>

        <div class="thread">
          <div
            v-for="(line, i) in lines"
            :key="i"
            class="row"
            :class="[speakerOf(line).voice === 'female' ? 'left' : 'right']"
          >
            <span class="who">{{ speakerOf(line).name }}</span>
            <button
              class="bubble"
              :class="{ active: current === i }"
              :title="'Replay ' + speakerOf(line).name"
              @click="playLine(i)"
            >
              <p v-if="showAllFi" class="line-fi">{{ line.fi }}</p>
              <p v-else class="line-hidden muted">· · ·</p>
            </button>
          </div>
        </div>
      </template>
    </template>

    <button class="btn btn-block continue-btn" :class="heardOnce || completed ? 'btn-primary' : 'btn-ghost'" @click="finishStep">
      {{ heardOnce || completed ? 'Continue' : 'Skip listening' }} <ArrowRight class="cont-ico" aria-hidden="true" />
    </button>
  </div>
</template>

<style scoped>
.listen-step { display: flex; flex-direction: column; gap: 14px; flex: 1; }

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
.step-sub { font-size: 13px; line-height: 1.4; }
.step-why { font-size: 12.5px; line-height: 1.45; margin-top: 4px; }

.no-audio { text-align: center; padding: 18px; }

.controls { display: flex; gap: 8px; flex-wrap: wrap; }
.play-btn { flex: 1; min-width: 170px; display: inline-flex; align-items: center; justify-content: center; gap: 7px; padding: 13px 18px; }
.ctl-btn { display: inline-flex; align-items: center; gap: 6px; padding: 13px 14px; font-size: 14px; }
.ctl-ico { width: 16px; height: 16px; flex-shrink: 0; }

.thread { display: flex; flex-direction: column; gap: 9px; }
.row { display: flex; flex-direction: column; gap: 3px; max-width: 86%; }
.row.left { align-self: flex-start; align-items: flex-start; }
.row.right { align-self: flex-end; align-items: flex-end; }
.who { font-size: 11px; font-weight: 800; color: var(--text-faint); letter-spacing: 0.04em; text-transform: uppercase; }
.bubble {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 16px;
  padding: 10px 13px;
  cursor: pointer;
  text-align: left;
  font-family: inherit;
  color: inherit;
  transition: border-color 0.2s ease, background 0.2s ease;
}
.row.left .bubble { border-bottom-left-radius: 6px; }
.row.right .bubble { border-bottom-right-radius: 6px; background: var(--accent-soft); border-color: rgba(245, 158, 11, 0.28); }
.bubble.active { border-color: var(--accent); box-shadow: 0 0 0 2px var(--accent-soft); }
.line-fi { font-weight: 700; font-size: 15px; line-height: 1.4; }
.line-hidden { font-size: 15px; letter-spacing: 3px; }

.continue-btn { margin-top: auto; display: inline-flex; align-items: center; justify-content: center; gap: 7px; }
.cont-ico { width: 16px; height: 16px; flex-shrink: 0; }
</style>
