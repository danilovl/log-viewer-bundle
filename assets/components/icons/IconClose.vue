<template>
  <svg
    :class="className"
    :width="width"
    :height="height"
    viewBox="0  0  24  24"
    fill="none"
    stroke="currentColor"
    :stroke-width="strokeWidth"
    stroke-linecap="round"
    stroke-linejoin="round"
  >
    <path d="M18  6L6  18M6  6l12  12"></path>
  </svg>
</template>

<script setup lang="ts">
withDefaults(
  defineProps<{
    className?: string
    width?: number
    height?: number
    strokeWidth?: number
  }>(),
  {
    className: '',
    width: 24,
    height: 24,
    strokeWidth: 2,
  },
)
</script>
