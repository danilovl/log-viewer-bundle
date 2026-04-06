<template>
  <div class="dashboard-skeleton">
    <div class="stats-skeleton-grid">
      <div v-for="i in 4" :key="i" class="skeleton-card">
        <div class="skeleton-line title"></div>
        <div class="skeleton-line value"></div>
      </div>
    </div>

    <div class="dashboard-section skeleton-chart-section">
      <div class="skeleton-line title" style="width: 200px; margin-bottom: 1.5rem"></div>
      <div class="skeleton-card chart-skeleton-box"></div>
    </div>

    <div class="dashboard-section">
      <div class="skeleton-line title" style="width: 150px; margin-bottom: 1.5rem"></div>
      <div class="stats-skeleton-grid small">
        <div v-for="i in 8" :key="i" class="skeleton-card mini"></div>
      </div>
    </div>
  </div>
</template>
