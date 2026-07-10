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
import { useAuthStore } from '../stores/auth'

const props = defineProps({
  lessons: { type: Array, required: true }
})

const auth = useAuthStore()

// Checkpoint state per level: a low-stakes recall quiz over everything
// studied at that level. Opens at 5 studied sentences (backend MIN_STUDIED).
function checkpointFor(level) {
  const started = props.lessons
    .filter((l) => l.level === level)
    .reduce((sum, l) => sum + (l.started_count ?? 0), 0)
  return {
    level,
    passed: !!auth.user?.checkpoints?.[level],
    available: started >= 5
  }
}

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

    // A divider before the first lesson of a new CEFR level; the checkpoint
    // for the level just completed sits on that boundary.
    const levelStart = i > 0 && prev && prev.level !== lesson.level ? lesson.level : null
    const checkpoint = levelStart ? checkpointFor(prev.level) : null

    return { lesson, status, index: i, recommended, levelStart, checkpoint }
  })
})

// The final level's checkpoint caps the end of the path.
const endCheckpoint = computed(() => {
  const last = props.lessons[props.lessons.length - 1]
  return last ? checkpointFor(last.level) : null
})

// Overall level progress, e.g. "A0 · 3/32 mastered". Number() guards against
// APIs serving SQL aggregates as strings (host-dependent) — += would concat.
const levelSummary = computed(() => {
  const byLevel = {}
  props.lessons.forEach((l) => {
    byLevel[l.level] ??= { mastered: 0, total: 0 }
    byLevel[l.level].mastered += Number(l.mastered_count) || 0
    byLevel[l.level].total += Number(l.sentences_count) || 0
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
      <template v-if="n.levelStart">
        <component
          :is="n.checkpoint.available ? 'router-link' : 'div'"
          :to="n.checkpoint.available ? `/checkpoint/${n.checkpoint.level}` : undefined"
          class="checkpoint-chip"
          :class="{ passed: n.checkpoint.passed, locked: !n.checkpoint.available }"
        >
          <template v-if="n.checkpoint.passed">🏅 {{ n.checkpoint.level }} checkpoint passed — retake any time</template>
          <template v-else-if="n.checkpoint.available">🎯 Take the {{ n.checkpoint.level }} checkpoint</template>
          <template v-else>🔒 {{ n.checkpoint.level }} checkpoint — opens after 5 studied sentences</template>
        </component>
        <div class="level-divider">
          <span class="level-line"></span>
          <span class="level-label">Level {{ n.levelStart }} begins</span>
          <span class="level-line"></span>
        </div>
      </template>
      <PathNode
        :lesson="n.lesson"
        :status="n.status"
        :index="n.index"
        :recommended="n.recommended"
        :is-last="i === nodes.length - 1"
      />
    </template>

    <component
      v-if="endCheckpoint"
      :is="endCheckpoint.available ? 'router-link' : 'div'"
      :to="endCheckpoint.available ? `/checkpoint/${endCheckpoint.level}` : undefined"
      class="checkpoint-chip end"
      :class="{ passed: endCheckpoint.passed, locked: !endCheckpoint.available }"
    >
      <template v-if="endCheckpoint.passed">🏅 {{ endCheckpoint.level }} checkpoint passed — retake any time</template>
      <template v-else-if="endCheckpoint.available">🎯 Take the {{ endCheckpoint.level }} checkpoint</template>
      <template v-else>🔒 {{ endCheckpoint.level }} checkpoint — opens after 5 studied sentences</template>
    </component>
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
  margin: 4px 0 14px;
  transition: filter 0.15s ease;
}
a.checkpoint-chip:hover { filter: brightness(1.1); }
.checkpoint-chip.passed {
  color: var(--green);
  background: var(--green-soft);
  border-color: var(--green);
  border-style: solid;
}
.checkpoint-chip.locked {
  color: var(--text-faint);
  background: var(--bg-soft);
  border-color: var(--border);
  cursor: default;
}
.checkpoint-chip.end { margin-top: 10px; }

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
