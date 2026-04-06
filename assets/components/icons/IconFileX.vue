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
    <path d="M14.5  2H6a2  2  0  0  0-2  2v16a2  2  0  0  0  2  2h12a2  2  0  0  0  2-2V7.5L14.5  2z"></path>
    <polyline points="14.5  2  14.5  7.5  20  7.5"></polyline>
    <line x1="9" y1="12" x2="15" y2="18"></line>
    <line x1="15" y1="12" x2="9" y2="18"></line>
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
