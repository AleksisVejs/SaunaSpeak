<script setup>
// The brand glyph: löyly rising off the stones. Drawn on the same 24px
// stroke grid as Lucide (stroke 2, round caps, currentColor) so it sits
// next to library icons without looking imported from somewhere else.
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
    <!-- three curls of steam -->
    <path d="M7 3c-1.5 2-1.5 4 0 6s1.5 4 0 6" />
    <path d="M12 3c-1.5 2-1.5 4 0 6s1.5 4 0 6" />
    <path d="M17 3c-1.5 2-1.5 4 0 6s1.5 4 0 6" />
    <!-- the kiuas stones -->
    <path d="M5 21h.01M10 21h.01M15 21h.01M20 21h.01" />
  </svg>
</template>
