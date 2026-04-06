<template>
  <svg
    :width="width"
    :height="height"
    viewBox="0  0  24  24"
    fill="none"
    stroke="currentColor"
    :stroke-width="strokeWidth"
    stroke-linecap="round"
    stroke-linejoin="round"
  >
    <path d="M23  4v6h-6" />
    <path d="M20.49  15a9  9  0  1  1-2.12-9.36L23  10" />
  </svg>
</template>

<script setup lang="ts">
defineProps({
  width: { type: [Number, String], default: 20 },
  height: { type: [Number, String], default: 20 },
  strokeWidth: { type: Number, default: 2 },
})
</script>
