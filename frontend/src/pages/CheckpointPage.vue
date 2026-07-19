<script setup>
// Level checkpoint: a short, low-stakes cumulative recall quiz. The framing
// matters - taking the test IS practice (testing effect), so failing is
// explicitly fine and retakes are always open.
//
// Intake placement (?intake=1): straight after onboarding, non-beginners are
// offered this as a test-out ladder - pass A1 and it offers A2, then B1, B2,
// each pass unlocking the path and skipping that level's fresh material. The
// chain carries ?go=1 so only the first step shows the offer. Declining (or
// not placing out of the first level) falls back to the coarse seed placement.
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Brain, Check, Eye, Rocket, Volume2, X } from 'lucide-vue-next'
import LoylyIcon from '../components/icons/LoylyIcon.vue'
import api from '../api'
import { useAuthStore } from '../stores/auth'
import { useFinnishAudio } from '../composables/useFinnishAudio'
import { usePrefs } from '../composables/usePrefs'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const { playSentence } = useFinnishAudio()
const { seedPlacement } = usePrefs()

// The test-out ladder for intake placement. A0 survival basics are the seeded
// floor, so the ladder starts at A1; only levels the curriculum actually has.
const INTAKE_LADDER = ['A1', 'A2', 'B1', 'B2']

const level = computed(() => String(route.params.level || 'A0').toUpperCase())
const intake = computed(() => route.query.intake === '1')
const nextPlacementLevel = computed(() => {
  const i = INTAKE_LADDER.indexOf(level.value)
  return i >= 0 && i < INTAKE_LADDER.length - 1 ? INTAKE_LADDER[i + 1] : null
})

const loading = ref(true)
const ready = ref(false)
// Placement mode: quizzed over the whole level (not just studied material)
// because the learner hasn't studied it - passing tests them out of it.
const placement = ref(false)
const studied = ref(0)
const needed = ref(5)
const sentences = ref([])
const index = ref(0)
const revealed = ref(false)
const correct = ref(0)
const finished = ref(false)
const passed = ref(false)
const xpGained = ref(0)
const submitting = ref(false)
// Intake only: the offer (take the check vs. start fresh) gates the first step;
// chained steps auto-start via ?go=1.
const accepted = ref(false)
const showOffer = computed(() => intake.value && !accepted.value && !finished.value)

const current = computed(() => sentences.value[index.value] || null)
const total = computed(() => sentences.value.length)
const progressPct = computed(() => (total.value ? Math.round((index.value / total.value) * 100) : 0))
const scorePct = computed(() => (total.value ? Math.round((correct.value / total.value) * 100) : 0))

async function load() {
  loading.value = true
  try {
    const { data } = await api.get(`/checkpoint/${level.value}`)
    ready.value = data.ready
    if (data.ready) {
      placement.value = !!data.placement
      sentences.value = data.sentences
    } else {
      studied.value = data.studied
      needed.value = data.needed
    }
  } finally {
    loading.value = false
  }
}

// (Re)start for the current route - reset the quiz, then either wait on the
// offer (the first intake step) or load straight into the quiz.
function start() {
  ready.value = false
  sentences.value = []
  index.value = 0
  revealed.value = false
  correct.value = 0
  finished.value = false
  passed.value = false
  xpGained.value = 0
  submitting.value = false
  accepted.value = !intake.value || route.query.go === '1'
  if (accepted.value) {
    load()
  } else {
    loading.value = false
  }
}

function accept() {
  accepted.value = true
  load()
}

// Decline / didn't test out: apply the coarse seed so they still get the head
// start their self-reported level earns, then into the app.
async function startFresh() {
  await seedPlacement()
  router.push('/dashboard')
}

function continueChain() {
  router.push({ name: 'checkpoint', params: { level: nextPlacementLevel.value }, query: { intake: '1', go: '1' } })
}

// End the intake chain. Placed out of nothing (failed or quit the very first
// level) → fall back to the seed; a pass anywhere means they're already placed.
function finishIntake() {
  if (!passed.value && level.value === INTAKE_LADDER[0]) return startFresh()
  router.push('/dashboard')
}

async function reveal() {
  if (revealed.value) return
  revealed.value = true
  playSentence(current.value.finnish_text, current.value.audio_url)
}

async function mark(got) {
  if (!revealed.value || submitting.value) return
  if (got) correct.value++

  if (index.value + 1 >= total.value) {
    submitting.value = true
    try {
      const { data } = await api.post(`/checkpoint/${level.value}`, {
        correct: correct.value,
        total: total.value
      })
      passed.value = data.passed
      xpGained.value = data.xp_gained
      await auth.fetchUser()
    } catch {
      // Score still shows locally; badge will save on a retake.
      passed.value = correct.value / total.value >= 0.8
    } finally {
      submitting.value = false
      finished.value = true
    }
  } else {
    index.value++
    revealed.value = false
  }
}

function onKey(e) {
  if (finished.value || !ready.value || showOffer.value) return
  if (e.code === 'Space') {
    e.preventDefault()
    reveal()
  } else if (revealed.value) {
    if (e.key === '1') mark(false)
    else if (e.key === '2') mark(true)
  }
}

// Chaining to the next level reuses this component (same route name), so react
// to the param/query change as well as the initial mount.
watch(() => route.fullPath, start)

onMounted(() => {
  start()
  window.addEventListener('keydown', onKey)
})
onUnmounted(() => window.removeEventListener('keydown', onKey))
</script>

<template>
  <div class="checkpoint">
    <div v-if="loading" class="spinner"></div>

    <!-- Not enough studied material yet -->
    <div v-else-if="!ready && !showOffer" class="panel">
      <img class="big-icon vaino" src="/vaino-oops.png" alt="Väinö shrugging" />
      <h1>Not quite yet</h1>
      <p class="muted">
        The {{ level }} checkpoint opens after you've studied {{ needed }} sentences -
        you're at {{ studied }}. A Sauna Session or two will get you there.
      </p>
      <router-link to="/session" class="btn btn-primary btn-block loyly-cta"><LoylyIcon class="cta-ico" aria-hidden="true" /> Start a session</router-link>
      <button class="btn btn-ghost btn-block" @click="router.push('/dashboard')">Back</button>
    </div>

    <!-- Result -->
    <div v-else-if="finished" class="panel">
      <img
        class="big-icon vaino"
        :src="passed ? '/vaino-medal.png' : '/vaino-flex.png'"
        :alt="passed ? 'Väinö holding a gold medal' : 'Väinö flexing encouragement'"
      />
      <h1>{{ passed ? (placement ? `You tested out of ${level}!` : `${level} checkpoint passed!`) : 'Good training!' }}</h1>
      <div class="score" :class="{ pass: passed }">{{ correct }}/{{ total }} · {{ scorePct }}%</div>
      <p v-if="passed && xpGained" class="xp-note">+{{ xpGained }} XP badge bonus</p>
      <p class="muted">
        {{ passed
          ? (placement
            ? `The path past ${level} is unlocked and your daily sessions skip straight to the next level. The ${level} lessons stay open whenever you want them.`
            : 'Your badge is on the journey path. Retake it any time - recalling is rehearsing.')
          : (placement
            ? `Not this time - you need 80% to place out of ${level}. Its lessons will get you there fast, and you can retake this any time.`
            : 'No pressure - every attempt strengthens the memories it touched. Do a session or two and come back; you need 80% to pass.') }}
      </p>
      <!-- Intake placement: pass → offer the next rung; fail/last → into the app -->
      <template v-if="intake">
        <button v-if="passed && nextPlacementLevel" class="btn btn-primary btn-block loyly-cta" @click="continueChain">
          <Rocket class="cta-ico" aria-hidden="true" /> Try {{ nextPlacementLevel }} next
        </button>
        <button
          class="btn btn-block"
          :class="passed && nextPlacementLevel ? 'btn-ghost' : 'btn-primary'"
          @click="finishIntake"
        >
          {{ passed ? 'Start learning' : `Start at ${level}` }}
        </button>
      </template>
      <router-link v-else to="/dashboard" class="btn btn-primary btn-block">Back to the path</router-link>
    </div>

    <!-- Intake placement offer: test out of levels already known, or start fresh -->
    <div v-else-if="showOffer" class="panel">
      <img class="big-icon vaino" src="/vaino-flex.png" alt="Väinö flexing encouragement" />
      <h1>Know some already?</h1>
      <p class="muted">
        Take a quick check and skip straight past the levels you already know -
        each one you pass unlocks the path and earns a badge. Or start from the
        very beginning, no pressure.
      </p>
      <button class="btn btn-primary btn-block loyly-cta" @click="accept">
        <Rocket class="cta-ico" aria-hidden="true" /> Take the quick check
      </button>
      <button class="btn btn-ghost btn-block" @click="startFresh">Start from the beginning</button>
    </div>

    <!-- Active quiz -->
    <div v-else class="quiz">
      <div class="quiz-top">
        <button class="quit" @click="intake ? finishIntake() : router.push('/dashboard')" aria-label="Quit"><X class="quit-ico" aria-hidden="true" /></button>
        <div class="progress-track"><div class="progress-fill" :style="{ width: progressPct + '%' }"></div></div>
        <span class="counter">{{ index + 1 }}/{{ total }}</span>
      </div>

      <p class="stakes-note">
        <template v-if="placement"><Rocket class="sn-ico" aria-hidden="true" /> Placement: score 80% and skip straight past {{ level }}.</template>
        <template v-else>Low stakes: taking this quiz is itself practice. Say each one out loud.</template>
      </p>

      <div class="card quiz-card">
        <p class="hint"><Brain class="hint-ico" aria-hidden="true" /> {{ level }} {{ placement ? 'placement' : 'checkpoint' }} - say it in Finnish</p>
        <p class="prompt">{{ current.english_text }}</p>

        <template v-if="revealed">
          <p class="answer">{{ current.finnish_text }}</p>
          <button class="replay" @click="playSentence(current.finnish_text, current.audio_url)"><Volume2 class="sn-ico" aria-hidden="true" /> Hear it again</button>
        </template>
        <button v-else class="btn btn-ghost reveal-btn" @click="reveal"><Eye class="sn-ico" aria-hidden="true" /> Show the Finnish</button>
      </div>

      <div v-if="revealed" class="marks">
        <button class="mark miss" :disabled="submitting" @click="mark(false)">
          <X class="mk-ico" aria-hidden="true" /> Missed it <span class="key">1</span>
        </button>
        <button class="mark got" :disabled="submitting" @click="mark(true)">
          <Check class="mk-ico" aria-hidden="true" /> Got it <span class="key">2</span>
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.checkpoint { min-height: 100vh; min-height: 100dvh; display: flex; flex-direction: column; padding: max(16px, 3vh) 4px 24px; }

.panel { margin: auto 0; text-align: center; display: flex; flex-direction: column; gap: 12px; }
.big-icon { font-size: 56px; }
.big-icon.vaino { width: 132px; height: 132px; margin: 0 auto; }
.panel h1 { font-size: 26px; }
.panel .muted { line-height: 1.55; margin-bottom: 8px; }
.score { font-size: 30px; font-weight: 800; color: var(--text-dim); }
.score.pass { color: var(--green); }
.xp-note { color: var(--accent); font-weight: 700; }

.quiz { display: flex; flex-direction: column; gap: 16px; flex: 1; }
.quiz-top { display: flex; align-items: center; gap: 12px; }
.quit { background: none; border: none; color: var(--text-dim); cursor: pointer; font-family: inherit; display: inline-flex; }
.quit-ico { width: 19px; height: 19px; }
.quiz-top .progress-track { flex: 1; }
.counter { font-size: 13px; color: var(--text-dim); font-weight: 600; }

.stakes-note { text-align: center; color: var(--text-dim); font-size: 13px; }
.sn-ico { width: 13px; height: 13px; vertical-align: -2px; }
.mk-ico { width: 15px; height: 15px; flex-shrink: 0; }
.loyly-cta { display: flex; align-items: center; justify-content: center; gap: 7px; }
.cta-ico { width: 16px; height: 16px; flex-shrink: 0; }

.quiz-card { display: flex; flex-direction: column; gap: 16px; padding: 26px 22px; }
.quiz-card .hint {
  color: var(--text-dim);
  font-size: 13px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  display: flex;
  align-items: center;
  gap: 6px;
}
.hint-ico { width: 14px; height: 14px; color: var(--accent); flex-shrink: 0; }
.prompt { font-size: 22px; font-weight: 700; line-height: 1.4; }
.answer { font-size: 26px; font-weight: 800; line-height: 1.35; color: var(--accent); }
.replay {
  align-self: flex-start;
  background: none;
  border: 1px solid var(--border);
  border-radius: var(--radius-pill);
  color: var(--text-dim);
  font-family: inherit;
  font-size: 13px;
  font-weight: 700;
  padding: 8px 14px;
  cursor: pointer;
}
.replay:hover { border-color: var(--accent); color: var(--accent); }
.reveal-btn { align-self: flex-start; padding: 10px 16px; font-size: 14px; }

.marks { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.mark {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 16px 8px;
  border-radius: var(--radius-sm);
  border: 1px solid var(--border);
  background: var(--card);
  color: var(--text);
  font-family: inherit;
  font-weight: 700;
  font-size: 15px;
  cursor: pointer;
}
.mark:disabled { opacity: 0.5; }
.mark .key { font-size: 11px; color: var(--text-faint); font-weight: 600; }
.mark.miss:hover { border-color: var(--red); color: var(--red); }
.mark.got:hover { border-color: var(--green); color: var(--green); }
</style>
