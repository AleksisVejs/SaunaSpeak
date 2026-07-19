<script setup>
// The brand glyph: löyly rising off the kiuas stones. Drawn on the same 24px
// stroke grid as Lucide (stroke 2, round caps, currentColor) so it sits next
// to library icons without looking imported from somewhere else.
// Three wisps of staggered height read as real rising steam rather than three
// identical clones, and the stones form a low mound instead of loose dots.
defineProps({
  size: { type: [Number, String], default: 24 },
  strokeWidth: { type: [Number, String], default: 2 }
})
</script>

<template>
  <svg
    :width="size"
    :height="size"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    :stroke-width="strokeWidth"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
  >
    <!-- three wisps of steam, rising to staggered heights -->
    <path d="M7 9.5c-1.1 1-1.1 2 0 3s1.1 2 0 3" />
    <path d="M12 4.5c-1.3 1.8-1.3 3.7 0 5.5s1.3 3.7 0 5.5" />
    <path d="M17 8c-1.1 1.3-1.1 2.5 0 3.75s1.1 2.5 0 3.75" />
    <!-- the kiuas: a low mound of heated stones -->
    <path d="M5 20a7 3.5 0 0 1 14 0z" />
    <path d="M9.6 17.4v2.6" />
    <path d="M14.4 17.4v2.6" />
  </svg>
</template>
