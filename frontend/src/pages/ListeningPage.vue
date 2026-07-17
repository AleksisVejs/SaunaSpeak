<script setup>
// Kuuntelu catalog: whole conversations, played at natural speed.
//
// The rest of the course is sentence-shaped, which is how items get memorized
// - but comprehension only comes from volume of connected speech. This is the
// extensive-listening half of the method, and it's free for that reason.
import { computed, onMounted, ref } from 'vue'
import { CircleCheck, Headphones } from 'lucide-vue-next'
import api from '../api'

const scenes = ref([])
const loading = ref(true)

onMounted(async () => {
  try {
    const { data } = await api.get('/listening')
    scenes.value = data.scenes
  } finally {
    loading.value = false
  }
})

const doneCount = computed(() => scenes.value.filter((s) => s.done).length)
</script>

<template>
  <div class="listening">
    <header class="head">
      <h2><Headphones class="head-ico" aria-hidden="true" /> Kuuntelu</h2>
      <p class="muted lede">
        Whole conversations at normal speed - two Finns, no pauses to translate in.
        Listen first, read the transcript only when you want it. This is the part
        that trains your ear for real life.
      </p>
      <p v-if="scenes.length" class="muted progress-line">
        {{ doneCount }} / {{ scenes.length }} listened through
      </p>
    </header>

    <div v-if="loading" class="spinner"></div>

    <div v-else class="scene-grid">
      <router-link
        v-for="s in scenes"
        :key="s.id"
        :to="`/listening/${s.id}`"
        class="card scene"
        :class="{ done: s.done }"
      >
        <span class="scene-emoji">{{ s.emoji }}</span>
        <div class="scene-body">
          <p class="scene-title">
            {{ s.title }}
            <CircleCheck v-if="s.done" class="done-ico" aria-hidden="true" />
          </p>
          <p class="scene-tagline muted">{{ s.tagline }}</p>
          <p class="scene-meta muted">{{ s.level }} · {{ s.lines_count }} lines</p>
        </div>
      </router-link>
    </div>
  </div>
</template>

<style scoped>
.listening { display: flex; flex-direction: column; gap: 18px; }
.head h2 { font-size: 24px; display: inline-flex; align-items: center; gap: 9px; }
.head-ico { width: 22px; height: 22px; color: var(--accent); }
.lede { font-size: 14px; line-height: 1.55; margin-top: 6px; }
.progress-line { font-size: 12.5px; font-weight: 700; margin-top: 8px; }

.scene-grid { display: grid; grid-template-columns: 1fr; gap: 10px; }
@media (min-width: 620px) { .scene-grid { grid-template-columns: 1fr 1fr; } }

.scene {
  display: flex;
  gap: 12px;
  align-items: flex-start;
  padding: 14px;
  transition: border-color 0.15s ease, transform 0.15s ease;
}
.scene:hover { border-color: var(--accent); transform: translateY(-1px); }
.scene.done { border-color: color-mix(in srgb, var(--green) 45%, transparent); }
.scene-emoji { font-size: 26px; line-height: 1.1; flex-shrink: 0; }
.scene-body { min-width: 0; }
.scene-title {
  font-weight: 800;
  font-size: 15px;
  color: var(--text);
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.done-ico { width: 15px; height: 15px; color: var(--green); flex-shrink: 0; }
.scene-tagline { font-size: 13px; line-height: 1.45; margin-top: 3px; }
.scene-meta { font-size: 11.5px; font-weight: 700; letter-spacing: 0.03em; margin-top: 6px; }
</style>
