<script setup>
// Taivutus catalog: the drills that make you CHANGE a sentence.
//
// Everywhere else the course hands you a whole sentence to learn. That's fast,
// but Finnish keeps its meaning in the endings - and knowing "Mä otan kahvin"
// by heart doesn't get you "Mä en ota kahvii". These sets drill exactly that
// move, one rule at a time.
import { computed, onMounted, ref } from 'vue'
import { CircleCheck, Wrench } from 'lucide-vue-next'
import api from '../api'
import { pathStageName } from '../utils/pathStages'

const sets = ref([])
const loading = ref(true)

onMounted(async () => {
  try {
    const { data } = await api.get('/transforms')
    sets.value = data.sets
  } finally {
    loading.value = false
  }
})

const doneCount = computed(() => sets.value.filter((s) => s.done).length)
</script>

<template>
  <div class="transforms">
    <header class="head">
      <h2><Wrench class="head-ico" aria-hidden="true" /> Taivutus</h2>
      <p class="muted lede">
        Finnish hides its grammar in the endings. Here you don't recall a sentence -
        you bend one: make it negative, make it yesterday, make it a question.
        This is what turns sentences you know into Finnish you can build.
      </p>
      <p v-if="sets.length" class="muted progress-line">{{ doneCount }} / {{ sets.length }} sets cleared</p>
    </header>

    <div v-if="loading" class="spinner"></div>

    <div v-else class="set-grid">
      <router-link
        v-for="s in sets"
        :key="s.id"
        :to="`/transforms/${s.id}`"
        class="card set"
        :class="{ done: s.done }"
      >
        <div class="set-top">
          <span class="set-emoji">{{ s.emoji }}</span>
          <p class="set-title">
            {{ s.title }}
            <CircleCheck v-if="s.done" class="done-ico" aria-hidden="true" />
          </p>
        </div>
        <p class="set-rule">{{ s.rule }}</p>
        <p class="set-meta muted">{{ pathStageName(s.level) }} · {{ s.items_count }} transforms</p>
      </router-link>
    </div>
  </div>
</template>

<style scoped>
.transforms { display: flex; flex-direction: column; gap: 18px; }
.head h2 { font-size: 24px; display: inline-flex; align-items: center; gap: 9px; }
.head-ico { width: 21px; height: 21px; color: var(--accent); }
.lede { font-size: 14px; line-height: 1.55; margin-top: 6px; }
.progress-line { font-size: 12.5px; font-weight: 700; margin-top: 8px; }

.set-grid { display: grid; grid-template-columns: 1fr; gap: 10px; }
@media (min-width: 620px) { .set-grid { grid-template-columns: 1fr 1fr; } }

.set { padding: 14px; transition: border-color 0.15s ease, transform 0.15s ease; }
.set:hover { border-color: var(--accent); transform: translateY(-1px); }
.set.done { border-color: color-mix(in srgb, var(--green) 45%, transparent); }
.set-top { display: flex; align-items: center; gap: 9px; }
.set-emoji { font-size: 22px; }
.set-title { font-weight: 800; font-size: 15px; color: var(--text); display: inline-flex; align-items: center; gap: 6px; }
.done-ico { width: 15px; height: 15px; color: var(--green); flex-shrink: 0; }
.set-rule { font-size: 13px; color: var(--accent); font-weight: 600; margin-top: 7px; line-height: 1.4; }
.set-meta { font-size: 11.5px; font-weight: 700; letter-spacing: 0.03em; margin-top: 6px; }
</style>
