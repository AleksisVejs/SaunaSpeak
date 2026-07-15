<script setup>
// Turns the flat lessons array into a guided, sequentially-unlocked journey,
// grouped into one collapsible section per CEFR level (A0 → C1) with a
// clickable level map on top. The level you're working on starts open;
// finished and future levels fold away so 40+ lessons stay scannable.
//
// Unlock rule: the first lesson is always open; each later lesson opens once
// the previous one has been STARTED (any sentence in the SRS). This mirrors
// the daily session engine, which feeds fresh sentences in lesson order -
// the map should never show a lock on ground the session already covers.
import { computed, ref, watch } from 'vue'
import PathNode from './PathNode.vue'
import { useAuthStore } from '../stores/auth'

const props = defineProps({
  lessons: { type: Array, required: true }
})

const auth = useAuthStore()

// Short names give each CEFR code a human hook on the map and headers.
const LEVEL_NAMES = {
  A0: 'First words',
  A1: 'Everyday life',
  A2: 'Stories & opinions',
  B1: 'Real world',
  B2: 'The native layer',
  C1: 'Sounding local'
}

// Checkpoint state per level. Always takeable: studied learners get a quiz
// over their material, everyone else gets a placement quiz over the level -
// passing either unlocks the path beyond it.
function checkpointFor(level) {
  return {
    level,
    passed: !!auth.user?.checkpoints?.[level]
  }
}

const levelPassed = (level) => !!auth.user?.checkpoints?.[level]

function isMastered(l) {
  return l.sentences_count > 0 && l.mastered_count >= l.sentences_count
}

function isStarted(l) {
  return (l.started_count ?? l.mastered_count) > 0
}

const nodes = computed(() => {
  let recommendedFound = false
  return props.lessons.map((lesson, i) => {
    const prev = props.lessons[i - 1]
    // A lesson opens when the previous one was started - or when its whole
    // level was tested out of via a passed checkpoint (placement).
    const unlocked = i === 0 || (prev && (isStarted(prev) || levelPassed(prev.level)))

    let status
    if (!unlocked) status = 'locked'
    else if (isMastered(lesson)) status = 'mastered'
    else if (isStarted(lesson)) status = 'in-progress'
    else status = 'current'

    // Recommend the first open lesson that isn't finished yet - skipping
    // levels the learner tested out of (they're open but not the path forward).
    let recommended = false
    if (!recommendedFound && unlocked && !isMastered(lesson) && !levelPassed(lesson.level)) {
      recommended = true
      recommendedFound = true
    }

    return { lesson, status, index: i, recommended }
  })
})

// One section per CEFR level, in path order, with roll-up progress. Number()
// guards against APIs serving SQL aggregates as strings (host-dependent).
const sections = computed(() => {
  const secs = []
  for (const n of nodes.value) {
    let s = secs[secs.length - 1]
    if (!s || s.level !== n.lesson.level) {
      s = { level: n.lesson.level, nodes: [] }
      secs.push(s)
    }
    s.nodes.push(n)
  }
  return secs.map((s) => {
    const total = s.nodes.reduce((t, n) => t + (Number(n.lesson.sentences_count) || 0), 0)
    const mastered = s.nodes.reduce((t, n) => t + (Number(n.lesson.mastered_count) || 0), 0)
    const masteredLessons = s.nodes.filter((n) => n.status === 'mastered').length
    return {
      ...s,
      name: LEVEL_NAMES[s.level] ?? '',
      masteredLessons,
      pct: total ? Math.round((mastered / total) * 100) : 0,
      done: s.nodes.length > 0 && masteredLessons === s.nodes.length,
      locked: s.nodes.every((n) => n.status === 'locked'),
      current: s.nodes.some((n) => n.recommended),
      checkpoint: checkpointFor(s.level)
    }
  })
})

// Collapse state: seed once when lessons arrive - open the level being
// worked on (or the last one when everything is mastered).
const open = ref({})
let seeded = false
watch(
  sections,
  (secs) => {
    if (seeded || !secs.length) return
    seeded = true
    const active = secs.find((s) => s.current) ?? secs.find((s) => !s.done) ?? secs[secs.length - 1]
    open.value = { [active.level]: true }
  },
  { immediate: true }
)

function toggle(level) {
  open.value = { ...open.value, [level]: !open.value[level] }
}

function jumpTo(level) {
  if (!open.value[level]) open.value = { ...open.value, [level]: true }
  requestAnimationFrame(() => {
    document.getElementById(`level-${level}`)?.scrollIntoView({ behavior: 'smooth', block: 'start' })
  })
}
</script>

<template>
  <div class="lesson-path">
    <p class="path-explainer">
      Your daily Sauna Session works through these lessons in order and brings
      every sentence back until it's mastered - from your first words (A0) all
      the way to sounding local (C1).
    </p>

    <!-- the whole journey at a glance -->
    <div class="level-map" role="navigation" aria-label="Course levels">
      <button
        v-for="s in sections"
        :key="s.level"
        class="map-step"
        :class="{ done: s.done, current: s.current, locked: s.locked }"
        :title="`${s.level} · ${s.name} — ${s.masteredLessons}/${s.nodes.length} lessons mastered`"
        @click="jumpTo(s.level)"
      >
        <span class="map-code">{{ s.done ? (s.checkpoint.passed ? '🏅' : '✓') : s.level }}</span>
        <span class="map-track"><span class="map-fill" :style="{ width: s.pct + '%' }"></span></span>
        <span class="map-name">{{ s.name }}</span>
      </button>
    </div>

    <section
      v-for="s in sections"
      :id="`level-${s.level}`"
      :key="s.level"
      class="level-section"
      :class="{ done: s.done, current: s.current, locked: s.locked }"
    >
      <button class="section-head" :aria-expanded="open[s.level] ? 'true' : 'false'" @click="toggle(s.level)">
        <span class="section-badge">{{ s.done ? (s.checkpoint.passed ? '🏅' : '✓') : s.level }}</span>
        <span class="section-info">
          <span class="section-title">
            <template v-if="!s.done">{{ s.level }} · </template>{{ s.name }}
            <span v-if="s.current" class="section-now">You're here</span>
          </span>
          <span class="section-sub">
            {{ s.done ? 'Level mastered 🏅' : `${s.masteredLessons}/${s.nodes.length} lessons mastered` }}
          </span>
        </span>
        <span class="section-side">
          <span class="section-pct">{{ s.pct }}%</span>
          <svg class="chevron" :class="{ open: open[s.level] }" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M6 9l6 6 6-6" />
          </svg>
        </span>
      </button>

      <div v-if="open[s.level]" class="section-body">
        <PathNode
          v-for="(n, i) in s.nodes"
          :key="n.lesson.id"
          :lesson="n.lesson"
          :status="n.status"
          :index="n.index"
          :recommended="n.recommended"
          :is-last="i === s.nodes.length - 1"
        />
        <router-link
          :to="`/checkpoint/${s.checkpoint.level}`"
          class="checkpoint-chip"
          :class="{ passed: s.checkpoint.passed }"
        >
          <template v-if="s.checkpoint.passed">🏅 {{ s.checkpoint.level }} checkpoint passed - retake any time</template>
          <template v-else-if="s.locked">🎯 Know {{ s.level }} already? Test out and skip ahead</template>
          <template v-else>🎯 Take the {{ s.checkpoint.level }} checkpoint - pass to unlock the next level instantly</template>
        </router-link>
      </div>
    </section>
  </div>
</template>

<style scoped>
.lesson-path { display: flex; flex-direction: column; }

.path-explainer {
  color: var(--text-dim);
  font-size: 13px;
  line-height: 1.5;
  margin-bottom: 14px;
}

/* ---- level map ---- */
.level-map {
  display: grid;
  grid-template-columns: repeat(6, 1fr);
  gap: 6px;
  margin-bottom: 18px;
}
.map-step {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 5px;
  padding: 9px 4px 7px;
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  cursor: pointer;
  transition: border-color 0.15s ease, background 0.15s ease;
  min-width: 0;
}
.map-step:hover { background: var(--card-hover); }
.map-code {
  font-size: 13px;
  font-weight: 800;
  color: var(--text-dim);
  line-height: 1;
}
.map-track {
  width: 100%;
  height: 4px;
  background: var(--bg-soft);
  border-radius: 99px;
  overflow: hidden;
}
.map-fill {
  display: block;
  height: 100%;
  border-radius: 99px;
  background: linear-gradient(90deg, var(--accent), var(--accent-2));
  transition: width 0.4s ease;
}
.map-name {
  font-size: 9px;
  font-weight: 700;
  letter-spacing: 0.03em;
  text-transform: uppercase;
  color: var(--text-faint);
  max-width: 100%;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  line-height: 1;
}
.map-step.done .map-code { color: var(--green); }
.map-step.done .map-fill { background: var(--green); }
.map-step.done { border-color: var(--green); }
.map-step.current {
  border-color: var(--accent);
  background: var(--accent-soft);
}
.map-step.current .map-code,
.map-step.current .map-name { color: var(--accent); }
.map-step.locked .map-code { color: var(--text-faint); }

/* ---- level sections ---- */
.level-section {
  border: 1px solid var(--border);
  border-radius: var(--radius);
  background: var(--card);
  margin-bottom: 12px;
  overflow: hidden;
  scroll-margin-top: 12px;
}
.level-section.current { border-color: var(--accent); }
.level-section.done { border-color: var(--green); }

.section-head {
  display: flex;
  align-items: center;
  gap: 12px;
  width: 100%;
  padding: 13px 14px;
  background: none;
  border: none;
  cursor: pointer;
  text-align: left;
  color: var(--text);
  transition: background 0.15s ease;
}
.section-head:hover { background: var(--card-hover); }

.section-badge {
  width: 38px;
  height: 38px;
  flex-shrink: 0;
  display: grid;
  place-items: center;
  border-radius: 12px;
  font-size: 14px;
  font-weight: 800;
  background: var(--bg-soft);
  color: var(--text-dim);
  border: 2px solid var(--border);
}
.level-section.current .section-badge {
  background: var(--accent-soft);
  color: var(--accent);
  border-color: var(--accent);
}
.level-section.done .section-badge {
  background: var(--green-soft);
  color: var(--green);
  border-color: var(--green);
}
.level-section.locked .section-badge { opacity: 0.55; }

.section-info { flex: 1; min-width: 0; }
.section-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 15px;
  font-weight: 800;
}
.section-now {
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--accent);
  background: var(--accent-soft);
  border-radius: var(--radius-pill);
  padding: 3px 8px;
  white-space: nowrap;
}
.section-sub {
  display: block;
  font-size: 12px;
  color: var(--text-dim);
  margin-top: 2px;
}
.level-section.done .section-sub { color: var(--green); }

.section-side {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-shrink: 0;
}
.section-pct {
  font-size: 12px;
  font-weight: 800;
  color: var(--text-dim);
}
.level-section.done .section-pct { color: var(--green); }
.level-section.current .section-pct { color: var(--accent); }
.chevron {
  width: 17px;
  height: 17px;
  color: var(--text-faint);
  transition: transform 0.2s ease;
}
.chevron.open { transform: rotate(180deg); }

.section-body { padding: 4px 14px 12px; }

/* ---- checkpoint chip caps its level ---- */
.checkpoint-chip {
  display: block;
  text-align: center;
  font-size: 13px;
  font-weight: 700;
  color: var(--accent);
  background: var(--accent-soft);
  border: 1px dashed var(--accent);
  border-radius: var(--radius-sm);
  padding: 11px 14px;
  transition: filter 0.15s ease;
}
a.checkpoint-chip:hover { filter: brightness(1.1); }
.checkpoint-chip.passed {
  color: var(--green);
  background: var(--green-soft);
  border-color: var(--green);
  border-style: solid;
}
</style>
