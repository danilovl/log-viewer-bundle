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
    <path d="M12  8V4H8" />
    <rect width="16" height="12" x="4" y="8" rx="2" />
    <path d="M2  14h2" />
    <path d="M20  14h2" />
    <path d="M15  13v2" />
    <path d="M9  13v2" />
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
