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
    <path d="M21  15v4a2  2  0  0  1-2  2H5a2  2  0  0  1-2-2v4"></path>
    <polyline points="7  10  12  15  17  10"></polyline>
    <line x1="12" y1="15" x2="12" y2="3"></line>
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
