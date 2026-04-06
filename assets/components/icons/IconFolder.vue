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
    <path
      d="M4  20h16a2  2  0  0  0  2-2V8a2  2  0  0  0-2-2h-7.93a2  2  0  0  1-1.66-.9l-.82-1.2A2  2  0  0  0  7.93  3H4a2  2  0  0  0-2  2v13a2  2  0  0  0  2  2Z"
    ></path>
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
