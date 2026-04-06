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
    <polygon points="13  2  3  14  12  14  11  22  21  10  12  10  13  2"></polygon>
  </svg>
</template>

<script setup lang="ts">
interface Props {
  width?: number
  height?: number
  strokeWidth?: number
}

withDefaults(defineProps<Props>(), {
  width: 24,
  height: 24,
  strokeWidth: 2,
})
</script>
