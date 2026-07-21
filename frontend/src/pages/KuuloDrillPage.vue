<script setup>
// One Kuulo set, run as forced-choice identification.
//
// The shape is High Variability Phonetic Training (Logan, Lively & Pisoni
// 1991): hear one item, choose between two, find out immediately. The
// immediacy is the active ingredient - delayed feedback trains much less
// well, because the sound is gone by the time you learn you were wrong.
//
// Every pair is asked in both directions so the answer can't be inferred from
// which word appeared; order is shuffled for the same reason.
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ArrowLeft, Check, RotateCcw, Volume2, X } from 'lucide-vue-next'
import api from '../api'
import VowelCheck from '../components/VowelCheck.vue'
import { useFinnishAudio } from '../composables/useFinnishAudio'

const route = useRoute()
const router = useRouter()
const { playClip, stop } = useFinnishAudio()

const set = ref(null)
const loading = ref(true)
const trials = ref([])
const at = ref(0)
const picked = ref(null)
const correct = ref(0)
const finished = ref(false)

const trial = computed(() => trials.value[at.value] ?? null)
const total = computed(() => trials.value.length)

// The production step unlocks only on a clearly-heard set. Discrimination
// precedes production (Logan, Lively & Pisoni 1991): a learner who is still
// guessing which vowel they heard cannot yet self-correct the one they say,
// and drilling it at that point trains the wrong sound in. 80% rather than
// perfect - the point is a working ear, not a flawless run.
const earReady = computed(() => total.value > 0 && correct.value / total.value >= 0.8)

onMounted(async () => {
  try {
    const { data } = await api.get(`/pairs/${route.params.id}`)
    set.value = data.set
    trials.value = buildTrials(data.set.pairs)
    playCurrent()
  } finally {
    loading.value = false
  }
})

// Each pair twice - once with 'a' as the answer, once with 'b' - so a learner
// who notices "it's always the first one" learns nothing.
function buildTrials(pairs) {
  const out = []
  for (const p of pairs) {
    if (p.a_audio) out.push({ ...p, answer: 'a', audio: p.a_audio })
    if (p.b_audio) out.push({ ...p, answer: 'b', audio: p.b_audio })
  }
  for (let i = out.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1))
    ;[out[i], out[j]] = [out[j], out[i]]
  }
  return out
}

function playCurrent() {
  if (trial.value?.audio) playClip(trial.value.audio)
}

function pick(side) {
  if (picked.value) return
  picked.value = side
  if (side === trial.value.answer) correct.value++
}

function next() {
  picked.value = null
  if (at.value + 1 >= total.value) {
    finished.value = true
    api.post(`/pairs/${route.params.id}/complete`).catch(() => {})
    return
  }
  at.value++
  playCurrent()
}

function again() {
  trials.value = buildTrials(set.value.pairs)
  at.value = 0
  picked.value = null
  correct.value = 0
  finished.value = false
  playCurrent()
}

function leave() {
  stop()
  router.push('/kuulo')
}
</script>

<template>
  <div class="drill">
    <div v-if="loading" class="spinner"></div>

    <template v-else-if="set">
      <header class="head">
        <button class="back" @click="leave"><ArrowLeft class="back-ico" aria-hidden="true" /> Kuulo</button>
        <p class="counter muted" v-if="!finished">{{ at + 1 }} / {{ total }}</p>
      </header>

      <template v-if="!finished">
        <p class="muted prompt">Which word did you hear?</p>

        <button class="replay" @click="playCurrent">
          <Volume2 class="replay-ico" aria-hidden="true" /> Play again
        </button>

        <div class="choices">
          <button
            v-for="side in ['a', 'b']"
            :key="side"
            class="choice"
            :class="{
              chosen: picked === side,
              right: picked && side === trial.answer,
              wrong: picked === side && side !== trial.answer
            }"
            :disabled="!!picked"
            @click="pick(side)"
          >
            <span class="word">{{ trial[side] }}</span>
            <span class="gloss" :class="{ shown: !!picked }">{{ trial[side + '_en'] }}</span>
            <Check v-if="picked && side === trial.answer" class="mark" aria-hidden="true" />
            <X v-else-if="picked === side" class="mark" aria-hidden="true" />
          </button>
        </div>

        <div v-if="picked" class="after">
          <p class="verdict" :class="picked === trial.answer ? 'ok' : 'no'">
            {{ picked === trial.answer ? 'Yes - that was ' : 'It was ' }}<strong>{{ trial[trial.answer] }}</strong>
          </p>
          <button class="btn btn-primary" @click="next">
            {{ at + 1 >= total ? 'Finish' : 'Next' }}
          </button>
        </div>
      </template>

      <section v-else class="done card">
        <h2>{{ correct }} / {{ total }}</h2>
        <p class="muted">
          {{ correct === total
            ? 'Every one. Your ear has the contrast.'
            : 'Getting these wrong is normal - the distinction is not in English, so it takes repeated hearing before it becomes obvious.' }}
        </p>
        <div class="done-actions">
          <button class="btn btn-primary" @click="again"><RotateCcw class="again-ico" aria-hidden="true" /> Again</button>
          <button class="btn" @click="leave">Back to Kuulo</button>
        </div>
      </section>

      <VowelCheck v-if="finished && earReady" :set="set" />
    </template>
  </div>
</template>

<style scoped>
.drill { display: flex; flex-direction: column; gap: 16px; }
.head { display: flex; align-items: center; justify-content: space-between; }
.back { display: inline-flex; align-items: center; gap: 6px; background: none; border: none; padding: 0; color: var(--text-dim); font-size: 14px; font-weight: 600; cursor: pointer; }
.back-ico { width: 16px; height: 16px; }
.counter { font-size: 13px; font-weight: 700; }

.prompt { text-align: center; font-size: 14.5px; }

.replay {
  display: inline-flex; align-items: center; justify-content: center; gap: 8px;
  align-self: center; padding: 14px 22px; border-radius: var(--radius-pill);
  border: 1px solid var(--border); background: var(--card);
  font-size: 15px; font-weight: 700; color: var(--text); cursor: pointer;
  transition: transform 0.12s var(--ease);
}
.replay:active { transform: scale(0.97); }
.replay-ico { width: 18px; height: 18px; color: var(--accent); }

.choices { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.choice {
  position: relative; display: flex; flex-direction: column; align-items: center; gap: 4px;
  padding: 22px 12px; border-radius: 14px; border: 1.5px solid var(--border);
  background: var(--card); cursor: pointer; transition: border-color 0.12s var(--ease), background 0.12s var(--ease);
}
.choice:disabled { cursor: default; }
.choice .word { font-size: 22px; font-weight: 800; }
.choice .gloss { font-size: 12.5px; color: var(--text-dim); opacity: 0; transition: opacity 0.15s var(--ease); }
.choice .gloss.shown { opacity: 1; }
.choice.right { border-color: var(--accent); background: var(--accent-soft); }
.choice.wrong { border-color: var(--danger, #d2544b); }
.mark { position: absolute; top: 8px; right: 8px; width: 16px; height: 16px; color: var(--text-dim); }

.after { display: flex; flex-direction: column; align-items: center; gap: 10px; }
.verdict { font-size: 14.5px; }
.verdict.ok { color: var(--accent); }
.verdict.no { color: var(--text-dim); }

.done { text-align: center; padding: 26px 20px; }
.done h2 { font-size: 30px; }
.done p { margin-top: 8px; font-size: 14px; line-height: 1.6; }
.done-actions { display: flex; justify-content: center; gap: 10px; margin-top: 18px; flex-wrap: wrap; }
.again-ico { width: 16px; height: 16px; }
</style>
