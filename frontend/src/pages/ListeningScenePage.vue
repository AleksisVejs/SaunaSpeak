<script setup>
// One Kuuntelu scene: a two-voice conversation played back to back.
//
// Comprehension first, text second - that's the whole point. The transcript
// starts HIDDEN: a learner who reads along is practising reading, not
// listening. Lines light up as they play, and the Finnish only appears when
// asked for (per line, or all at once). English is one more tap after that,
// so the ladder is: hear it → hear it again slowly → read it → translate it.
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { CircleCheck, Eye, EyeOff, Languages, Pause, Play, Repeat, Turtle, Volume2, X } from 'lucide-vue-next'
import api from '../api'
import { useAuthStore } from '../stores/auth'
import { useFinnishAudio } from '../composables/useFinnishAudio'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const { playSentence, playSentenceAsync, stop } = useFinnishAudio()

const SLOW_RATE = 0.7

const scene = ref(null)
const loading = ref(true)
const playing = ref(false)
const current = ref(-1) // index of the line being played, -1 = none
const showAllFi = ref(false)
const revealedFi = ref({}) // index → true (per-line transcript override)
const revealedEn = ref({})
const finished = ref(false)
const xpGained = ref(0)

// A playthrough is only "real" if it ran to the last line - a run token lets
// a newer play/stop cancel the loop an older one is still inside.
let runToken = 0

onMounted(async () => {
  try {
    const { data } = await api.get(`/listening/${route.params.id}`)
    scene.value = data.scene
    finished.value = data.scene.done
  } finally {
    loading.value = false
  }
})

onUnmounted(() => {
  runToken++
  stop()
})

const lines = computed(() => scene.value?.lines ?? [])
// No generated audio (fresh checkout, generation not run) → say so instead of
// rendering a play button that does nothing.
const hasAudio = computed(() => lines.value.some((l) => l.audio_url))

function speakerOf(line) {
  return scene.value?.speakers?.[line.who] ?? { name: line.who, voice: 'male' }
}

function fiShown(i) {
  return showAllFi.value || revealedFi.value[i] === true
}
function toggleFi(i) {
  revealedFi.value[i] = !fiShown(i)
}
function toggleEn(i) {
  revealedEn.value[i] = !revealedEn.value[i]
}

// Play the whole conversation back to back - the closest thing here to
// hearing Finnish happen at you.
async function playAll(rate = null) {
  if (playing.value) return stopPlayback()

  const token = ++runToken
  playing.value = true

  for (let i = 0; i < lines.value.length; i++) {
    if (token !== runToken) return
    current.value = i
    await playSentenceAsync(lines.value[i].fi, lines.value[i].audio_url, rate)
    if (token !== runToken) return
    // A beat between turns: without it the two voices trample each other and
    // the dialogue stops sounding like people taking turns.
    await new Promise((r) => setTimeout(r, 320))
  }

  if (token !== runToken) return
  playing.value = false
  current.value = -1
  markComplete()
}

function stopPlayback() {
  runToken++
  stop()
  playing.value = false
  current.value = -1
}

// Replay one line (the move a learner makes when a line slipped past them).
function playLine(i, rate = null) {
  stopPlayback()
  current.value = i
  playSentence(lines.value[i].fi, lines.value[i].audio_url, rate)
}

async function markComplete() {
  if (finished.value) return
  try {
    const { data } = await api.post(`/listening/${route.params.id}/complete`)
    xpGained.value = data.xp_gained
    finished.value = true
    await auth.fetchUser()
  } catch {
    // Listening still happened - the badge just won't stick this time.
    finished.value = true
  }
}
</script>

<template>
  <div class="scene-page">
    <div v-if="loading" class="spinner"></div>

    <template v-else-if="scene">
      <header class="top">
        <button class="quit" aria-label="Back to Kuuntelu" @click="router.push('/listening')">
          <X class="quit-ico" aria-hidden="true" />
        </button>
        <div class="top-meta">
          <p class="top-title">{{ scene.emoji }} {{ scene.title }}</p>
          <p class="top-sub muted">{{ scene.tagline }}</p>
        </div>
      </header>

      <div v-if="!hasAudio" class="card no-audio">
        <p>This scene has no audio yet.</p>
        <p class="muted tiny">Run <code>php artisan listening:audio</code> to generate it.</p>
      </div>

      <!-- the controls that matter: hear the whole thing, or slow it down -->
      <div v-else class="controls">
        <button class="btn btn-primary play-btn" @click="playAll()">
          <component :is="playing ? Pause : Play" class="ctl-ico" aria-hidden="true" />
          {{ playing ? 'Stop' : finished ? 'Listen again' : 'Play conversation' }}
        </button>
        <button class="btn btn-ghost ctl-btn" title="Play the whole thing slowly" @click="playAll(SLOW_RATE)">
          <Turtle class="ctl-ico" aria-hidden="true" /> Slow
        </button>
        <button class="btn btn-ghost ctl-btn" :title="showAllFi ? 'Hide the transcript' : 'Show the transcript'" @click="showAllFi = !showAllFi">
          <component :is="showAllFi ? EyeOff : Eye" class="ctl-ico" aria-hidden="true" />
          {{ showAllFi ? 'Hide text' : 'Show text' }}
        </button>
      </div>

      <p v-if="hasAudio && !showAllFi" class="muted hint">
        Try it with the text hidden first - understanding without reading is the skill.
      </p>

      <!-- the conversation itself -->
      <div class="thread">
        <div
          v-for="(line, i) in lines"
          :key="i"
          class="row"
          :class="[speakerOf(line).voice === 'female' ? 'left' : 'right', { active: current === i }]"
        >
          <span class="who">{{ speakerOf(line).name }}</span>
          <div class="bubble" :class="{ active: current === i }">
            <div class="bubble-top">
              <button class="line-play" :title="'Replay ' + speakerOf(line).name" @click="playLine(i)">
                <Volume2 class="line-ico" aria-hidden="true" />
              </button>
              <button class="line-play" title="Replay slowly" @click="playLine(i, SLOW_RATE)">
                <Turtle class="line-ico" aria-hidden="true" />
              </button>
              <button class="line-play" :title="fiShown(i) ? 'Hide Finnish' : 'Show Finnish'" @click="toggleFi(i)">
                <component :is="fiShown(i) ? EyeOff : Eye" class="line-ico" aria-hidden="true" />
              </button>
              <button class="line-play" :title="revealedEn[i] ? 'Hide English' : 'Show English'" @click="toggleEn(i)">
                <Languages class="line-ico" aria-hidden="true" />
              </button>
            </div>
            <p v-if="fiShown(i)" class="line-fi">{{ line.fi }}</p>
            <p v-else class="line-hidden muted">· · ·</p>
            <p v-if="revealedEn[i]" class="line-en muted">{{ line.en }}</p>
          </div>
        </div>
      </div>

      <div v-if="finished" class="card done-card">
        <p class="done-title"><CircleCheck class="done-ico" aria-hidden="true" /> Listened through</p>
        <p v-if="xpGained" class="done-xp">+{{ xpGained }} XP</p>
        <p class="muted tiny">
          Come back to this one in a few days - the second time is where you notice
          how much more you catch.
        </p>
        <div class="done-actions">
          <button class="btn btn-ghost btn-block" @click="playAll()"><Repeat class="ctl-ico" aria-hidden="true" /> Listen again</button>
          <router-link to="/listening" class="btn btn-primary btn-block">More conversations</router-link>
        </div>
      </div>
    </template>
  </div>
</template>

<style scoped>
.scene-page { display: flex; flex-direction: column; gap: 16px; }

.top { display: flex; align-items: flex-start; gap: 12px; }
.quit { background: none; border: none; color: var(--text-dim); cursor: pointer; padding: 2px; display: inline-flex; }
.quit:hover { color: var(--text); }
.quit-ico { width: 19px; height: 19px; }
.top-meta { min-width: 0; }
.top-title { font-weight: 800; font-size: 18px; }
.top-sub { font-size: 13px; margin-top: 2px; line-height: 1.4; }

.no-audio { text-align: center; padding: 20px; }
.no-audio code { font-size: 12px; background: var(--bg-soft); padding: 2px 5px; border-radius: 4px; }
.tiny { font-size: 12px; }

.controls { display: flex; gap: 8px; flex-wrap: wrap; }
.play-btn { flex: 1; min-width: 180px; display: inline-flex; align-items: center; justify-content: center; gap: 7px; padding: 13px 18px; }
.ctl-btn { display: inline-flex; align-items: center; gap: 6px; padding: 13px 14px; font-size: 14px; }
.ctl-ico { width: 16px; height: 16px; flex-shrink: 0; }
.hint { font-size: 12.5px; text-align: center; }

.thread { display: flex; flex-direction: column; gap: 10px; }
.row { display: flex; flex-direction: column; gap: 3px; max-width: 86%; }
.row.left { align-self: flex-start; align-items: flex-start; }
.row.right { align-self: flex-end; align-items: flex-end; }
.who { font-size: 11px; font-weight: 800; color: var(--text-faint); letter-spacing: 0.04em; text-transform: uppercase; }

.bubble {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 16px;
  padding: 10px 13px;
  transition: border-color 0.2s ease, background 0.2s ease;
}
.row.left .bubble { border-bottom-left-radius: 6px; }
.row.right .bubble { border-bottom-right-radius: 6px; background: var(--accent-soft); border-color: rgba(245, 158, 11, 0.28); }
/* The line currently sounding - the only "follow along" cue when text is hidden. */
.bubble.active { border-color: var(--accent); box-shadow: 0 0 0 2px var(--accent-soft); }

.bubble-top { display: flex; gap: 2px; margin-bottom: 4px; }
.line-play {
  background: none;
  border: none;
  color: var(--text-faint);
  cursor: pointer;
  padding: 3px 5px;
  border-radius: 6px;
  display: inline-flex;
}
.line-play:hover { color: var(--accent); background: var(--bg-soft); }
.line-ico { width: 14px; height: 14px; }

.line-fi { font-weight: 700; font-size: 15px; line-height: 1.4; }
.line-hidden { font-size: 15px; letter-spacing: 3px; }
.line-en { font-size: 12.5px; margin-top: 3px; line-height: 1.4; }

.done-card { text-align: center; display: flex; flex-direction: column; gap: 8px; }
.done-title { font-weight: 800; display: inline-flex; align-items: center; justify-content: center; gap: 7px; color: var(--green); }
.done-ico { width: 17px; height: 17px; flex-shrink: 0; }
.done-xp { font-weight: 800; color: var(--accent); font-size: 18px; }
.done-actions { display: flex; flex-direction: column; gap: 8px; margin-top: 6px; }
.done-actions .btn { display: inline-flex; align-items: center; justify-content: center; gap: 7px; }
</style>
