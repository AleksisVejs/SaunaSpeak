<script setup>
// Kuulo catalog: the vowel contrasts that actually break comprehension.
//
// Learners (and this app, until recently) worry about vowel LENGTH - tapan vs
// tapaan. Native speakers say that one resolves from context, and that whole
// dialects drop double consonants while staying perfectly clear. What does not
// survive is swapping the vowel itself: u for y, a for ä. Finnish vowel
// harmony runs on exactly that distinction, so one wrong stem vowel drags
// every ending after it the wrong way.
import { computed, onMounted, ref } from 'vue'
import { CircleCheck, Ear } from 'lucide-vue-next'
import api from '../api'

const sets = ref([])
const loading = ref(true)

onMounted(async () => {
  try {
    const { data } = await api.get('/pairs')
    sets.value = data.sets
  } finally {
    loading.value = false
  }
})

const doneCount = computed(() => sets.value.filter((s) => s.done).length)
const anyUnverified = computed(() => sets.value.some((s) => !s.verified))
</script>

<template>
  <div class="kuulo">
    <header class="head">
      <h2><Ear class="head-ico" aria-hidden="true" /> Kuulo</h2>
      <p class="muted lede">
        No speaking here - just listening. You hear one word and pick which of
        two it was. These are the vowels English doesn't have, and they're the
        ones Finns say actually stop them understanding a learner.
      </p>
      <p v-if="sets.length" class="muted progress-line">{{ doneCount }} / {{ sets.length }} sets cleared</p>
    </header>

    <p v-if="anyUnverified" class="review-note">
      Some sets are still awaiting a native speaker's check - words, meanings
      and audio. Treat them as a preview.
    </p>

    <div v-if="loading" class="spinner"></div>

    <div v-else class="set-grid">
      <router-link v-for="s in sets" :key="s.id" :to="`/kuulo/${s.id}`" class="card set">
        <div class="set-top">
          <span class="contrast">
            <span class="v front">{{ s.contrast[0] }}</span>
            <span class="vs">vs</span>
            <span class="v back">{{ s.contrast[1] }}</span>
          </span>
          <CircleCheck v-if="s.done" class="done-ico" aria-hidden="true" />
        </div>
        <p class="set-rule">{{ s.rule }}</p>
        <p class="muted set-meta">{{ s.pairs_count }} pairs</p>
      </router-link>
    </div>
  </div>
</template>

<style scoped>
.kuulo { display: flex; flex-direction: column; gap: 18px; }
.head h2 { display: flex; align-items: center; gap: 8px; font-size: 21px; }
.head-ico { width: 20px; height: 20px; color: var(--accent); }
.lede { margin-top: 8px; font-size: 14.5px; line-height: 1.6; }
.progress-line { margin-top: 8px; font-size: 13px; font-weight: 600; }

.review-note {
  padding: 10px 13px; border-radius: 10px; font-size: 13px; line-height: 1.5;
  background: var(--accent-soft); color: var(--text-dim); border: 1px solid var(--border);
}

.set-grid { display: grid; grid-template-columns: 1fr; gap: 12px; }
@media (min-width: 620px) { .set-grid { grid-template-columns: 1fr 1fr; } }

.set { display: flex; flex-direction: column; gap: 8px; padding: 16px; transition: transform 0.15s var(--ease), box-shadow 0.15s var(--ease); }
.set:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }
.set-top { display: flex; align-items: center; justify-content: space-between; }
.contrast { display: inline-flex; align-items: baseline; gap: 8px; }
.v { font-size: 26px; font-weight: 800; line-height: 1; }
.v.front { color: var(--accent); }
.v.back { color: var(--text-dim); }
.vs { font-size: 12px; font-weight: 700; color: var(--text-dim); }
.done-ico { width: 18px; height: 18px; color: var(--accent); }
.set-rule { font-size: 13.5px; line-height: 1.5; color: var(--text-dim); }
.set-meta { font-size: 12.5px; }
</style>
