<script setup>
// A single stop on the learning path. Locked nodes are non-interactive.
// The CEFR level lives on the surrounding section header, not the card.
import { computed } from 'vue'
import { Check, Lock } from 'lucide-vue-next'

const props = defineProps({
  lesson: { type: Object, required: true },
  status: { type: String, required: true }, // mastered | in-progress | current | locked
  index: { type: Number, required: true },
  isLast: { type: Boolean, default: false },
  recommended: { type: Boolean, default: false }
})

const pct = computed(() => {
  const t = props.lesson.sentences_count || 0
  return t ? Math.round((props.lesson.mastered_count / t) * 100) : 0
})

const locked = computed(() => props.status === 'locked')
</script>

<template>
  <div class="path-node" :class="[`is-${status}`, { recommended }]">
    <!-- left rail: connector + marker -->
    <div class="rail">
      <span class="marker">
        <Check v-if="status === 'mastered'" class="marker-ico" aria-label="Mastered" />
        <Lock v-else-if="locked" class="marker-ico" aria-label="Locked" />
        <template v-else>{{ index + 1 }}</template>
      </span>
      <span v-if="!isLast" class="connector"></span>
    </div>

    <!-- content -->
    <component
      :is="locked ? 'div' : 'router-link'"
      :to="locked ? undefined : `/lesson/${lesson.id}`"
      class="node-card"
      :class="{ locked }"
      :aria-disabled="locked ? 'true' : undefined"
    >
      <div class="node-head">
        <p class="node-title">{{ lesson.title }}</p>
        <span v-if="recommended" class="rec-tag">Start here →</span>
        <Check v-else-if="status === 'mastered'" class="done-tag" aria-label="Mastered" />
      </div>
      <div class="node-foot">
        <div class="mini-track"><div class="mini-fill" :style="{ width: pct + '%' }"></div></div>
        <span class="mini-count">{{ lesson.mastered_count }}/{{ lesson.sentences_count }}</span>
      </div>
    </component>
  </div>
</template>

<style scoped>
.path-node {
  display: grid;
  grid-template-columns: 40px 1fr;
  gap: var(--space-4);
}

/* rail */
.rail {
  display: flex;
  flex-direction: column;
  align-items: center;
}
.marker {
  width: 40px;
  height: 40px;
  flex-shrink: 0;
  border-radius: 50%;
  display: grid;
  place-items: center;
  font-weight: 800;
  font-size: 15px;
  background: var(--bg-soft);
  color: var(--text-dim);
  border: 2px solid var(--border);
  z-index: 1;
}
.connector {
  flex: 1;
  width: 3px;
  min-height: 22px;
  background: var(--border);
  margin: 2px 0;
}

.is-mastered .marker { background: var(--green-soft); color: var(--green); border-color: var(--green); }
.is-mastered .connector { background: var(--green); }
.is-in-progress .marker,
.is-current .marker { background: var(--accent-soft); color: var(--accent); border-color: var(--accent); }
.is-locked .marker { opacity: 0.55; }

.recommended .marker {
  box-shadow: 0 0 0 4px var(--accent-soft);
  animation: nodePulse 2.4s var(--ease) infinite;
}
@keyframes nodePulse {
  0%, 100% { box-shadow: 0 0 0 4px var(--accent-soft); }
  50% { box-shadow: 0 0 0 8px transparent; }
}

/* content card */
.node-card {
  display: block;
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 14px 16px;
  margin-bottom: var(--space-3);
  color: var(--text);
  transition: background 0.15s ease, transform 0.08s ease, border-color 0.15s ease;
}
.node-card:not(.locked):hover { background: var(--card-hover); }
.node-card:not(.locked):active { transform: scale(0.99); }
.recommended .node-card { border-color: var(--accent); }
.node-card.locked { opacity: 0.5; cursor: not-allowed; }

.node-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.rec-tag { font-size: var(--text-xs); font-weight: 800; color: var(--accent); white-space: nowrap; }
.done-tag { width: 16px; height: 16px; color: var(--green); flex-shrink: 0; }
.marker-ico { width: 16px; height: 16px; }
.node-title { font-weight: 700; font-size: 15px; }

.node-foot { display: flex; align-items: center; gap: 10px; margin-top: 10px; }
.mini-track { flex: 1; height: 6px; background: var(--bg-soft); border-radius: 99px; overflow: hidden; }
.mini-fill { height: 100%; border-radius: 99px; background: linear-gradient(90deg, var(--accent), var(--accent-2)); transition: width 0.4s ease; }
.is-mastered .mini-fill { background: var(--green); }
.mini-count { font-size: var(--text-xs); color: var(--text-dim); font-weight: 600; white-space: nowrap; }
</style>
