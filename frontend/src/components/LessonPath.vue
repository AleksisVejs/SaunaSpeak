<script setup>
// Turns the flat lessons array into a guided, sequentially-unlocked journey.
//
// Unlock rule: the first lesson is always open; each later lesson opens once
// the previous one has been STARTED (any sentence in the SRS). This mirrors
// the daily session engine, which feeds fresh sentences in lesson order —
// the map should never show a lock on ground the session already covers.
//
// Level milestones (A0 → A1) are drawn as dividers so learners can see the
// level boundary they're working toward.
import { computed } from 'vue'
import PathNode from './PathNode.vue'

const props = defineProps({
  lessons: { type: Array, required: true }
})

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
    const unlocked = i === 0 || (prev && isStarted(prev))

    let status
    if (!unlocked) status = 'locked'
    else if (isMastered(lesson)) status = 'mastered'
    else if (isStarted(lesson)) status = 'in-progress'
    else status = 'current'

    // Recommend the first open lesson that isn't finished yet.
    let recommended = false
    if (!recommendedFound && unlocked && !isMastered(lesson)) {
      recommended = true
      recommendedFound = true
    }

    // A divider before the first lesson of a new CEFR level.
    const levelStart = i > 0 && prev && prev.level !== lesson.level ? lesson.level : null

    return { lesson, status, index: i, recommended, levelStart }
  })
})

// Overall level progress, e.g. "A0 · 3/32 mastered".
const levelSummary = computed(() => {
  const byLevel = {}
  props.lessons.forEach((l) => {
    byLevel[l.level] ??= { mastered: 0, total: 0 }
    byLevel[l.level].mastered += l.mastered_count
    byLevel[l.level].total += l.sentences_count
  })
  return Object.entries(byLevel).map(([level, c]) => ({ level, ...c }))
})
</script>

<template>
  <div class="lesson-path">
    <p class="path-explainer">
      Your daily Sauna Session works through these lessons in order and brings
      every sentence back until it's mastered. Master the A0 lessons and the
      path carries you straight into A1.
    </p>

    <div class="level-summary">
      <span v-for="s in levelSummary" :key="s.level" class="level-chip">
        <b>{{ s.level }}</b> {{ s.mastered }}/{{ s.total }} mastered
      </span>
    </div>

    <template v-for="(n, i) in nodes" :key="n.lesson.id">
      <div v-if="n.levelStart" class="level-divider">
        <span class="level-line"></span>
        <span class="level-label">Level {{ n.levelStart }} begins</span>
        <span class="level-line"></span>
      </div>
      <PathNode
        :lesson="n.lesson"
        :status="n.status"
        :index="n.index"
        :recommended="n.recommended"
        :is-last="i === nodes.length - 1"
      />
    </template>
  </div>
</template>

<style scoped>
.lesson-path { display: flex; flex-direction: column; }

.path-explainer {
  color: var(--text-dim);
  font-size: 13px;
  line-height: 1.5;
  margin-bottom: 12px;
}

.level-summary { display: flex; gap: 8px; margin-bottom: 16px; }
.level-chip {
  font-size: var(--text-xs);
  color: var(--text-dim);
  background: var(--bg-soft);
  border: 1px solid var(--border);
  border-radius: var(--radius-pill);
  padding: 4px 10px;
}
.level-chip b { color: var(--accent); margin-right: 2px; }

.level-divider {
  display: flex;
  align-items: center;
  gap: 10px;
  margin: 6px 0 14px;
}
.level-line { flex: 1; height: 1px; background: var(--border); }
.level-label {
  font-size: var(--text-xs);
  font-weight: 800;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: var(--accent);
}
</style>
