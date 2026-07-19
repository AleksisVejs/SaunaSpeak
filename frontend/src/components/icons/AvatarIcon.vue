<script setup>
import { computed } from 'vue'

// The profile tab's glyph: a small disc carrying the learner's own initial,
// so the nav feels personal instead of reusing the brand steam mark. Sized by
// the .nav-icon / .tab-icon classes it's given, and tinted with currentColor
// so it lights up in the accent colour when the tab is active.
const props = defineProps({
  name: { type: String, default: '' }
})

const initial = computed(() => {
  const trimmed = (props.name || '').trim()
  return trimmed ? trimmed[0].toUpperCase() : '?'
})
</script>

<template>
  <span class="avatar-icon" aria-hidden="true">{{ initial }}</span>
</template>

<style scoped>
.avatar-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  background: color-mix(in srgb, currentColor 16%, transparent);
  box-shadow: inset 0 0 0 1.5px color-mix(in srgb, currentColor 55%, transparent);
  font-size: 11px;
  font-weight: 800;
  line-height: 1;
  letter-spacing: 0;
  /* Cancel the parent's letter-spacing/casing so the initial stays centred. */
  text-transform: none;
}
</style>
