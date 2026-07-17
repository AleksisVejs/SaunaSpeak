<script setup>
// One Taivutus set: bend the sentence, item by item.
//
// The shape is deliberate. The learner sees a sentence they can already say
// and an instruction ("say you're NOT having one"), and has to produce the
// changed form themselves - no multiple choice, because recognizing an ending
// isn't the same skill as generating one. The rule explanation lands AFTER
// the attempt: a guess made first is what makes the explanation stick.
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ArrowRight, CircleCheck, Eye, Lightbulb, Repeat, Wrench, X } from 'lucide-vue-next'
import api from '../api'
import { useAuthStore } from '../stores/auth'
import PracticeInput from '../components/PracticeInput.vue'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const set = ref(null)
const loading = ref(true)
const index = ref(0)
const answered = ref(false) // this item has been attempted or given up on
const gaveUp = ref(false)
const correctCount = ref(0)
const finished = ref(false)
const xpGained = ref(0)

onMounted(async () => {
  try {
    const { data } = await api.get(`/transforms/${route.params.id}`)
    set.value = data.set
  } finally {
    loading.value = false
  }
})

const items = computed(() => set.value?.items ?? [])
const current = computed(() => items.value[index.value] ?? null)
const total = computed(() => items.value.length)
const progressPct = computed(() => (total.value ? Math.round((index.value / total.value) * 100) : 0))

function onChecked(correct) {
  answered.value = true
  if (correct) correctCount.value++
}

function reveal() {
  answered.value = true
  gaveUp.value = true
}

async function next() {
  if (index.value + 1 >= total.value) {
    finished.value = true
    try {
      const { data } = await api.post(`/transforms/${route.params.id}/complete`)
      xpGained.value = data.xp_gained
      await auth.fetchUser()
    } catch {
      // The practice happened either way - only the badge is at risk.
    }
    return
  }
  index.value++
  answered.value = false
  gaveUp.value = false
}

function restart() {
  index.value = 0
  answered.value = false
  gaveUp.value = false
  correctCount.value = 0
  finished.value = false
  xpGained.value = 0
}
</script>

<template>
  <div class="drill-page">
    <div v-if="loading" class="spinner"></div>

    <template v-else-if="set">
      <!-- finished -->
      <div v-if="finished" class="card finish">
        <img class="finish-icon" src="/vaino-cheer.png" alt="Väinö cheering" />
        <h2>Set cleared!</h2>
        <p class="muted">
          {{ correctCount }} / {{ total }} bent right on the first try.
          <template v-if="correctCount < total"> The ones that fought back are the ones worth repeating.</template>
        </p>
        <p v-if="xpGained" class="xp">+{{ xpGained }} XP</p>
        <div class="finish-actions">
          <button class="btn btn-ghost btn-block" @click="restart"><Repeat class="btn-ico" aria-hidden="true" /> Run it again</button>
          <router-link to="/transforms" class="btn btn-primary btn-block">More sets</router-link>
        </div>
      </div>

      <!-- active drill -->
      <template v-else>
        <div class="top">
          <button class="quit" aria-label="Back to Taivutus" @click="router.push('/transforms')">
            <X class="quit-ico" aria-hidden="true" />
          </button>
          <div class="progress-track"><div class="progress-fill" :style="{ width: progressPct + '%' }"></div></div>
          <span class="counter">{{ index + 1 }}/{{ total }}</span>
        </div>

        <p class="rule-strip"><Wrench class="rule-ico" aria-hidden="true" /> {{ set.rule }}</p>

        <div class="card item">
          <p class="prompt">{{ current.prompt }}</p>

          <div class="from-row">
            <div class="from">
              <p class="from-fi">{{ current.from }}</p>
              <p class="from-en muted">{{ current.from_en }}</p>
            </div>
            <ArrowRight class="arrow" aria-hidden="true" />
            <div class="to">
              <p v-if="answered" class="to-fi">{{ current.to }}</p>
              <p v-else class="to-blank">?</p>
              <p v-if="answered" class="to-en muted">{{ current.to_en }}</p>
            </div>
          </div>

          <!-- The rule lands only after the attempt: guessing first is what
               makes the explanation stick. -->
          <transition name="fade">
            <p v-if="answered" class="note"><Lightbulb class="note-ico" aria-hidden="true" /> {{ current.note }}</p>
          </transition>
        </div>

        <PracticeInput
          :key="`t-${index}`"
          :expected="current.to"
          :translation="current.to_en"
          :accepts="current.accepts || []"
          :rejects="[current.from]"
          placeholder="Say or type the changed sentence"
          @checked="onChecked"
          @confirm="next"
        />

        <div class="actions">
          <button v-if="!answered" class="btn btn-ghost btn-block" @click="reveal">
            <Eye class="btn-ico" aria-hidden="true" /> Show me
          </button>
          <button v-else class="btn btn-primary btn-block" @click="next">
            {{ index + 1 >= total ? 'Finish' : 'Next' }} →
          </button>
        </div>
      </template>
    </template>
  </div>
</template>

<style scoped>
.drill-page { display: flex; flex-direction: column; gap: 14px; flex: 1; }

.top { display: flex; align-items: center; gap: 12px; }
.quit { background: none; border: none; color: var(--text-dim); cursor: pointer; padding: 2px; display: inline-flex; }
.quit:hover { color: var(--text); }
.quit-ico { width: 18px; height: 18px; }
.top .progress-track { flex: 1; }
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
.from-en { font-size: 12.5px; margin-top: 3px; line-height: 1.35; }
.arrow { width: 20px; height: 20px; color: var(--accent); flex-shrink: 0; }
.to-fi { font-size: 17px; font-weight: 800; color: var(--accent); line-height: 1.35; }
.to-en { font-size: 12.5px; margin-top: 3px; line-height: 1.35; }
.to-blank { font-size: 22px; font-weight: 800; color: var(--text-faint); }
@media (max-width: 480px) {
  .from-row { flex-direction: column; align-items: stretch; gap: 8px; }
  .arrow { transform: rotate(90deg); align-self: center; }
}

.note {
  font-size: 13px;
  line-height: 1.5;
  color: var(--text-dim);
  background: var(--bg-soft);
  border-radius: var(--radius-sm);
  padding: 11px 13px;
}
.note-ico { width: 14px; height: 14px; vertical-align: -2px; margin-right: 4px; color: var(--accent); }

.actions { margin-top: auto; }
.btn-ico { width: 16px; height: 16px; vertical-align: -3px; margin-right: 5px; }

.finish { text-align: center; margin-top: 6vh; display: flex; flex-direction: column; gap: 12px; }
.finish-icon { width: 132px; height: 132px; margin: 0 auto; }
.xp { font-weight: 800; color: var(--accent); font-size: 20px; }
.finish-actions { display: flex; flex-direction: column; gap: 8px; margin-top: 6px; }
</style>
