<script setup>
// Reuses the global .progress-track / .progress-fill styling.
import { computed } from 'vue'

const props = defineProps({
  value: { type: Number, default: 0 },   // 0..100
  label: { type: String, default: '' }
})

const pct = computed(() => Math.max(0, Math.min(100, Math.round(props.value))))
</script>

<template>
  <div
    class="progress-track"
    role="progressbar"
    :aria-valuenow="pct"
    aria-valuemin="0"
    aria-valuemax="100"
    :aria-label="label || undefined"
  >
    <div class="progress-fill" :style="{ width: pct + '%' }"></div>
  </div>
</template>
