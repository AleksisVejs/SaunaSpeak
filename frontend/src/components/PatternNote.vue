<script setup>
// "Why this works" - a short, dismissible grammar rule shown in context.
// Collapsed state is remembered per pattern so it doesn't nag on every visit.
import { ref } from 'vue'

const props = defineProps({
  pattern: { type: Object, required: true }
})

const key = `ss_pattern_open_${props.pattern.id}`
// Default open the first time a learner meets a pattern.
const open = ref(localStorage.getItem(key) !== '0')

function toggle() {
  open.value = !open.value
  localStorage.setItem(key, open.value ? '1' : '0')
}
</script>

<template>
  <div class="pattern" :class="{ open }">
    <button class="pattern-head" @click="toggle" :aria-expanded="open">
      <span class="bulb">💡</span>
      <span class="pattern-title">{{ pattern.title }}</span>
      <span class="chev">{{ open ? '▾' : '▸' }}</span>
    </button>

    <div v-if="open" class="pattern-body">
      <p class="summary">{{ pattern.summary }}</p>
      <ul class="examples">
        <li v-for="(ex, i) in pattern.examples" :key="i" class="example">
          <span class="ex-fi">{{ ex.fi }}</span>
          <span class="ex-en">{{ ex.en }}</span>
          <span v-if="ex.note" class="ex-note">{{ ex.note }}</span>
        </li>
      </ul>
    </div>
  </div>
</template>

<style scoped>
.pattern {
  background: var(--accent-soft);
  border: 1px solid color-mix(in srgb, var(--accent) 35%, transparent);
  border-radius: var(--radius);
  overflow: hidden;
}
.pattern-head {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  background: none;
  border: none;
  padding: 14px 16px;
  cursor: pointer;
  font-family: inherit;
  color: var(--text);
  text-align: left;
}
.bulb { font-size: 18px; }
.pattern-title { flex: 1; font-weight: 800; font-size: 15px; }
.chev { color: var(--accent); font-size: 13px; }

.pattern-body { padding: 0 16px 16px; }
.summary { color: var(--text); font-size: 14px; line-height: 1.55; margin-bottom: 12px; }
.examples { list-style: none; display: flex; flex-direction: column; gap: 8px; }
.example {
  display: grid;
  grid-template-columns: auto 1fr;
  gap: 4px 12px;
  align-items: baseline;
  background: var(--card);
  border-radius: var(--radius-sm);
  padding: 9px 12px;
}
.ex-fi { font-weight: 700; }
.ex-en { color: var(--text-dim); font-size: 14px; }
.ex-note { grid-column: 1 / -1; font-size: 12px; color: var(--text-faint); }
</style>
