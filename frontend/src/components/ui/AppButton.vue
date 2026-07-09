<script setup>
// Thin wrapper over the global .btn classes so screens compose a primitive
// instead of repeating class strings. Renders <button> or a <router-link>.
import { computed } from 'vue'

const props = defineProps({
  variant: { type: String, default: 'primary' }, // primary | ghost
  block: { type: Boolean, default: false },
  to: { type: [String, Object], default: null },
  type: { type: String, default: 'button' },
  disabled: { type: Boolean, default: false }
})

const classes = computed(() => [
  'btn',
  props.variant === 'ghost' ? 'btn-ghost' : 'btn-primary',
  { 'btn-block': props.block }
])
</script>

<template>
  <router-link v-if="to" :to="to" :class="classes">
    <slot />
  </router-link>
  <button v-else :class="classes" :type="type" :disabled="disabled">
    <slot />
  </button>
</template>
