<script setup>
// 2-minute intake: goal, level, daily time. Personalizes the daily goal and
// drops the learner straight into their first session (value in session one).
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { BicepsFlexed, BookOpen, Check, Coffee, Egg, Flame, Heart, Home, Moon, Plane, RotateCcw, Sprout, Sun, Sunset } from 'lucide-vue-next'
import LoylyIcon from '../components/icons/LoylyIcon.vue'
import { usePrefs } from '../composables/usePrefs'

const router = useRouter()
const { savePrefs } = usePrefs()

const step = ref(0)
const answers = ref({ goal: null, level: null, minutes: null, practice_time: null })

const questions = [
  {
    key: 'intro',
    kind: 'intro',
    icon: LoylyIcon,
    title: 'Tervetuloa!',
    text: "Three quick questions and we'll tune SaunaSpeak to you. You'll learn real spoken Finnish - what people actually say in shops, buses and saunas."
  },
  {
    key: 'goal',
    kind: 'choice',
    title: 'Why are you learning Finnish?',
    options: [
      { value: 'move', icon: Home, label: 'Moving to Finland' },
      { value: 'travel', icon: Plane, label: 'Travel & visits' },
      { value: 'family', icon: Heart, label: 'Family & friends' },
      { value: 'casual', icon: Sprout, label: 'Just curious' }
    ]
  },
  {
    key: 'level',
    kind: 'choice',
    title: 'How much Finnish do you know?',
    options: [
      { value: 'none', icon: Egg, label: 'Absolute beginner' },
      { value: 'some', icon: BookOpen, label: 'A few words' },
      { value: 'rusty', icon: RotateCcw, label: 'Rusty - brushing up' }
    ]
  },
  {
    key: 'minutes',
    kind: 'choice',
    title: 'How much time per day?',
    options: [
      { value: 2, icon: Coffee, label: '2 min - keep the habit alive' },
      { value: 5, icon: Flame, label: '5 min - steady' },
      { value: 15, icon: BicepsFlexed, label: '15 min - serious' }
    ]
  },
  {
    // Implementation intention: people who commit to a WHEN come back at
    // roughly double the rate of people who merely intend to. The answer
    // also times the review-reminder email to the learner's own rhythm.
    key: 'practice_time',
    kind: 'choice',
    title: 'When will you practice?',
    options: [
      { value: 'morning', icon: Sun, label: 'Morning - with coffee' },
      { value: 'lunch', icon: Sunset, label: 'Midday - on a break' },
      { value: 'evening', icon: Moon, label: 'Evening - winding down' }
    ]
  }
]

const current = computed(() => questions[step.value])
const isLast = computed(() => step.value === questions.length - 1)
const progress = computed(() => Math.round((step.value / (questions.length - 1)) * 100))

const canAdvance = computed(() => {
  if (current.value.kind === 'intro') return true
  return answers.value[current.value.key] != null
})

function choose(key, value) {
  answers.value[key] = value
  // Auto-advance on selection for a snappy feel, except the final step.
  if (!isLast.value) setTimeout(next, 180)
}

function next() {
  if (isLast.value) return finish()
  step.value++
}

function back() {
  if (step.value > 0) step.value--
}

function finish() {
  const beginner = answers.value.level === 'none'
  // Defer the coarse seed placement: for non-beginners it's the fallback if
  // they skip the test, applied later by the placement flow. (For beginners
  // it's a no-op anyway - nothing to skip.)
  savePrefs(
    {
      goal: answers.value.goal,
      level: answers.value.level,
      minutes: answers.value.minutes,
      practice_time: answers.value.practice_time
    },
    { placement: false }
  )
  // Beginners go straight into the first session - earn value immediately.
  // Everyone else is offered a placement check that tests them out of levels
  // they already know, starting at A1 (A0 survival basics are the seeded floor).
  if (beginner) {
    router.push({ name: 'session' })
  } else {
    router.push({ name: 'checkpoint', params: { level: 'everyday-life' }, query: { intake: '1' } })
  }
}
</script>

<template>
  <div class="onb">
    <div class="onb-top">
      <button v-if="step > 0" class="back" @click="back" aria-label="Back">‹</button>
      <div class="progress-track onb-progress"><div class="progress-fill" :style="{ width: progress + '%' }"></div></div>
    </div>

    <transition name="fade" mode="out-in">
      <div :key="current.key" class="onb-step">
        <template v-if="current.kind === 'intro'">
          <div class="intro-icon"><component :is="current.icon" class="intro-ico" aria-hidden="true" /></div>
          <h1>{{ current.title }}</h1>
          <p class="onb-text">{{ current.text }}</p>
        </template>

        <template v-else>
          <h1 class="q-title">{{ current.title }}</h1>
          <div class="options">
            <button
              v-for="opt in current.options"
              :key="opt.value"
              class="option"
              :class="{ selected: answers[current.key] === opt.value }"
              @click="choose(current.key, opt.value)"
            >
              <span class="opt-icon"><component :is="opt.icon" class="opt-ico" aria-hidden="true" /></span>
              <span class="opt-label">{{ opt.label }}</span>
              <span class="opt-check"><Check class="check-ico" aria-hidden="true" /></span>
            </button>
          </div>
        </template>
      </div>
    </transition>

    <button class="btn btn-primary btn-block onb-cta" :disabled="!canAdvance" @click="next">
      {{ current.kind === 'intro' ? "Let's go" : isLast ? 'Start my first session' : 'Continue' }}
    </button>
  </div>
</template>

<style scoped>
.onb {
  min-height: 100vh;
  min-height: 100dvh;
  display: flex;
  flex-direction: column;
  padding: max(24px, 6vh) 4px 28px;
}
.onb-top { display: flex; align-items: center; gap: 12px; margin-bottom: 40px; }
.back {
  background: none;
  border: none;
  color: var(--text-dim);
  font-size: 30px;
  line-height: 1;
  cursor: pointer;
  font-family: inherit;
}
.onb-progress { flex: 1; }

.onb-step { flex: 1; display: flex; flex-direction: column; }
.intro-icon { text-align: center; margin-bottom: 14px; color: var(--accent); }
.intro-ico { width: 56px; height: 56px; stroke-width: 1.5; }
.onb h1 { font-size: 26px; margin-bottom: 12px; }
.q-title { text-align: left; }
.onb-text { color: var(--text-dim); font-size: 16px; line-height: 1.55; }

.options { display: flex; flex-direction: column; gap: 12px; margin-top: 8px; }
.option {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 16px 18px;
  border-radius: var(--radius);
  background: var(--card);
  border: 2px solid var(--border);
  color: var(--text);
  font-family: inherit;
  font-size: 16px;
  font-weight: 600;
  cursor: pointer;
  text-align: left;
  transition: border-color 0.15s ease, background 0.15s ease, transform 0.08s ease;
}
.option:hover { background: var(--card-hover); }
.option:active { transform: scale(0.99); }
.option.selected { border-color: var(--accent); background: var(--accent-soft); }
.opt-icon { display: grid; place-items: center; color: var(--accent); }
.opt-ico { width: 22px; height: 22px; }
.check-ico { width: 16px; height: 16px; display: block; }
.opt-label { flex: 1; }
.opt-check { color: var(--accent); font-weight: 800; opacity: 0; transition: opacity 0.15s ease; }
.option.selected .opt-check { opacity: 1; }

.onb-cta { margin-top: 28px; font-size: 17px; padding: 16px; }
</style>
