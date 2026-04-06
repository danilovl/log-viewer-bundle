<template>
  <svg
    :width="width"
    :height="height"
    viewBox="0  0  24  24"
    fill="none"
    stroke="currentColor"
    stroke-width="2"
    stroke-linecap="round"
    stroke-linejoin="round"
    :class="className"
  >
    <path d="M8  3H5a2  2  0  0  0-2  2v3" />
    <path d="M21  8V5a2  2  0  0  0-2-2h-3" />
    <path d="M3  16v3a2  2  0  0  0  2  2h3" />
    <path d="M16  21h3a2  2  0  0  0  2-2v-3" />
  </svg>
</template>

<script setup lang="ts">
defineProps({
  width: {
    type: Number,
    default: 24,
  },
  height: {
    type: Number,
    default: 24,
  },
  className: {
    type: String,
    default: '',
  },
})
</script>
