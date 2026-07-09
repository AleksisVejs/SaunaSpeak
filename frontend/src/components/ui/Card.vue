<script setup>
// Surface primitive. `interactive` adds hover feedback; `as` lets it render
// as a router-link (for tappable list rows) while keeping one root element.
import { computed } from 'vue'

const props = defineProps({
  interactive: { type: Boolean, default: false },
  to: { type: [String, Object], default: null }
})

const classes = computed(() => ['ui-card', { 'ui-card--interactive': props.interactive || props.to }])
</script>

<template>
  <router-link v-if="to" :to="to" :class="classes">
    <slot />
  </router-link>
  <div v-else :class="classes">
    <slot />
  </div>
</template>

<style scoped>
.ui-card {
  display: block;
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: var(--space-5);
  color: var(--text);
}
.ui-card--interactive {
  transition: background 0.15s ease, transform 0.08s ease, box-shadow 0.15s ease;
  cursor: pointer;
}
.ui-card--interactive:hover { background: var(--card-hover); }
.ui-card--interactive:active { transform: scale(0.99); }
</style>
