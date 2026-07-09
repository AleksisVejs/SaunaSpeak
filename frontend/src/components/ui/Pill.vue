<script setup>
// Status / label pill. tone maps to a color family.
import { computed } from 'vue'

const props = defineProps({
  tone: { type: String, default: 'accent' } // accent | green | blue | red | neutral
})

const style = computed(() => {
  const map = {
    accent: ['var(--accent)', 'var(--accent-soft)'],
    green: ['var(--green)', 'var(--green-soft)'],
    blue: ['var(--blue)', 'var(--blue-soft)'],
    red: ['var(--red)', 'var(--red-soft)'],
    neutral: ['var(--text-dim)', 'var(--bg-soft)']
  }
  const [fg, bg] = map[props.tone] || map.accent
  return { color: fg, background: bg }
})
</script>

<template>
  <span class="ui-pill" :style="style"><slot /></span>
</template>

<style scoped>
.ui-pill {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: var(--text-xs);
  font-weight: 700;
  letter-spacing: 0.02em;
  padding: 3px 10px;
  border-radius: var(--radius-pill);
  white-space: nowrap;
}
</style>
