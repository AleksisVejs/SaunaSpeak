<script setup>
// 2-minute intake: goal, level, daily time. Personalizes the daily goal and
// drops the learner straight into their first session (value in session one).
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { usePrefs } from '../composables/usePrefs'

const router = useRouter()
const { savePrefs } = usePrefs()

const step = ref(0)
const answers = ref({ goal: null, level: null, minutes: null })

const questions = [
  {
    key: 'intro',
    kind: 'intro',
    icon: '🧖',
    title: 'Tervetuloa!',
    text: "Three quick questions and we'll tune SaunaSpeak to you. You'll learn real spoken Finnish - what people actually say in shops, buses and saunas."
  },
  {
    key: 'goal',
    kind: 'choice',
    title: 'Why are you learning Finnish?',
    options: [
      { value: 'move', icon: '🏡', label: 'Moving to Finland' },
      { value: 'travel', icon: '✈️', label: 'Travel & visits' },
      { value: 'family', icon: '❤️', label: 'Family & friends' },
      { value: 'casual', icon: '🌱', label: 'Just curious' }
    ]
  },
  {
    key: 'level',
    kind: 'choice',
    title: 'How much Finnish do you know?',
    options: [
      { value: 'none', icon: '🐣', label: 'Absolute beginner' },
      { value: 'some', icon: '📖', label: 'A few words' },
      { value: 'rusty', icon: '🔁', label: 'Rusty - brushing up' }
    ]
  },
  {
    key: 'minutes',
    kind: 'choice',
    title: 'How much time per day?',
    options: [
      { value: 2, icon: '☕', label: '2 min - a taste' },
      { value: 5, icon: '🔥', label: '5 min - steady' },
      { value: 15, icon: '💪', label: '15 min - serious' }
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
  savePrefs({
    goal: answers.value.goal,
    level: answers.value.level,
    minutes: answers.value.minutes
  })
  // Straight into the first session - earn value immediately.
  router.push({ name: 'session' })
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
          <div class="intro-icon">{{ current.icon }}</div>
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
              <span class="opt-icon">{{ opt.icon }}</span>
              <span class="opt-label">{{ opt.label }}</span>
              <span class="opt-check">✓</span>
            </button>
          </div>
        </template>
      </div>
    </transition>

    <button class="btn btn-primary btn-block onb-cta" :disabled="!canAdvance" @click="next">
      {{ current.kind === 'intro' ? "Let's go 🧖" : isLast ? 'Start my first session' : 'Continue' }}
    </button>
  </div>
</template>

<style scoped>
.onb {
  min-height: 100vh;
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
.intro-icon { font-size: 56px; text-align: center; margin-bottom: 14px; }
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
.opt-icon { font-size: 24px; }
.opt-label { flex: 1; }
.opt-check { color: var(--accent); font-weight: 800; opacity: 0; transition: opacity 0.15s ease; }
.option.selected .opt-check { opacity: 1; }

.onb-cta { margin-top: 28px; font-size: 17px; padding: 16px; }
</style>
