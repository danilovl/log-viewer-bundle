<template>
  <div class="log-skeleton">
    <div class="stats-skeleton-row">
      <div class="skeleton-card mini"></div>
      <div class="skeleton-card mini"></div>
    </div>

    <div class="stats-skeleton-grid">
      <div v-for="i in 5" :key="i" class="skeleton-card">
        <div class="skeleton-line title"></div>
        <div class="skeleton-line value"></div>
      </div>
    </div>

    <div class="log-card skeleton-table-card">
      <div class="skeleton-table-header">
        <div class="skeleton-line header-col" style="width: 230px"></div>
        <div class="skeleton-line header-col" style="width: 110px"></div>
        <div class="skeleton-line header-col" style="width: 130px"></div>
        <div class="skeleton-line header-col"></div>
      </div>
      <div class="skeleton-table-body">
        <div v-for="i in 10" :key="i" class="skeleton-table-row">
          <div class="skeleton-col" style="width: 230px">
            <div class="skeleton-line ts"></div>
            <div class="skeleton-line ts small"></div>
          </div>
          <div class="skeleton-col" style="width: 110px">
            <div class="skeleton-badge"></div>
          </div>
          <div class="skeleton-col" style="width: 130px">
            <div class="skeleton-line channel"></div>
          </div>
          <div class="skeleton-col">
            <div class="skeleton-line message"></div>
            <div class="skeleton-line message short"></div>
          </div>
        </div>
      </div>
      <div class="skeleton-pagination">
        <div class="skeleton-btn"></div>
        <div style="display: flex; gap: 0.5rem">
          <div v-for="i in 5" :key="i" class="skeleton-page"></div>
        </div>
        <div class="skeleton-btn"></div>
      </div>
    </div>
  </div>
</template>
