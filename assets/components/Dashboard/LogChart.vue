<template>
  <div class="chart-container full-width">
    <div class="chart-header">
      <h4>{{ t('errorsOverTime') }}</h4>
      <div class="chart-controls">
        <div class="control-group">
          <span class="control-label">{{ t('chartType') }}:</span>
          <div class="granularity-picker">
            <button
              v-for="type in ['area', 'bar', 'line'] as const"
              :key="type"
              class="granularity-btn"
              :class="{ active: chartType === type }"
              @click="chartType = type"
            >
              {{ t(type) }}
            </button>
          </div>
        </div>

        <div class="control-group">
          <span class="control-label">{{ t('level') }}:</span>
          <div class="granularity-picker">
            <button
              v-for="f in ['hour', 'day'] as TimelineFormat[]"
              :key="f"
              class="granularity-btn"
              :class="{ active: store.dashboard.timelineFormat === f }"
              @click="store.changeDashboardTimelineFormat(f)"
            >
              {{ t(f) }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <div class="apex-chart-wrapper">
      <ApexChart
        v-if="store.dashboard.stats"
        :key="store.dashboard.timelineFormat"
        height="350"
        :type="chartType"
        :options="chartOptions"
        :series="chartSeries"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import ApexChart from 'vue3-apexcharts'
import { useLogStore } from '@/stores/useLogStore'
import { useI18n } from '@/i18n/useI18n'
import { palette } from '@/utils/color'
import type { TimelineFormat } from '@/types'

const store = useLogStore()
const { t } = useI18n()

type ChartType = 'area' | 'bar' | 'line'
const chartType = ref<ChartType>('area')
const isDark = ref(document.documentElement.classList.contains('dark'))

const observer = new MutationObserver(() => {
  isDark.value = document.documentElement.classList.contains('dark')
})

onMounted(() => {
  observer.observe(document.documentElement, {
    attributes: true,
    attributeFilter: ['class'],
  })
})

onUnmounted(() => {
  observer.disconnect()
})

const chartSeries = computed(() => {
  const rawTimeline = store.dashboard.stats?.timeline || {}
  const data = Object.entries(rawTimeline)
    .map(([date, val]) => {
      const xDate = typeof date === 'string' ? new Date(date.replace(' ', 'T')).getTime() : 0

      return {
        x: xDate,
        y: val,
      }
    })
    .filter((item) => {
      return item.x > 0
    })
    .sort((a, b) => {
      return a.x - b.x
    })

  return [
    {
      id: 'main-total-entries',
      name: t('totalEntries'),
      data,
    },
  ]
})

const chartOptions = computed(() => {
  const textColor = isDark.value ? '#94a3b8' : '#64748b'
  const gridColor = isDark.value ? '#334155' : '#f1f5f9'

  return {
    chart: {
      id: 'log-viewer-dashboard-chart',
      type: chartType.value,
      toolbar: {
        show: true,
        tools: {
          download: true,
          selection: true,
          zoom: true,
          zoomin: true,
          zoomout: true,
          pan: true,
          reset: true,
        },
      },
      zoom: {
        enabled: true,
        type: 'x',
        autoScaleYaxis: true,
      },
      background: 'transparent',
      fontFamily: 'Inter, sans-serif',
      foreColor: textColor,
      animations: {
        enabled: true,
        easing: 'easeinout',
        speed: 800,
        animateGradually: {
          enabled: true,
          delay: 150,
        },
        dynamicAnimation: {
          enabled: true,
          speed: 350,
        },
      },
    },
    colors: [palette[0]],
    dataLabels: {
      enabled: false,
    },
    stroke: {
      curve: 'smooth',
      width: 3,
    },
    fill: {
      type: 'gradient',
      gradient: {
        shadeIntensity: 1,
        opacityFrom: 0.45,
        opacityTo: 0.05,
        stops: [20, 100],
      },
    },
    grid: {
      borderColor: gridColor,
      xaxis: {
        lines: {
          show: false,
        },
      },
      yaxis: {
        lines: {
          show: true,
        },
      },
    },
    xaxis: {
      type: 'datetime',
      tickAmount: store.dashboard.timelineFormat === 'hour' ? 12 : 10,
      labels: {
        datetimeUTC: false,
        hideOverlappingLabels: true,
        showDuplicates: false,
        style: {
          colors: textColor,
        },
        datetimeFormatter: {
          year: 'yyyy',
          month: "MMM 'yy",
          day: 'dd MMM',
          hour: 'HH:mm',
        },
      },
      axisBorder: {
        show: false,
      },
      axisTicks: {
        show: false,
      },
      tooltip: {
        enabled: true,
        formatter: (val: string) => {
          const date = new Date(Number.parseInt(val))
          if (store.dashboard.timelineFormat === 'hour') {
            return date.toLocaleString([], {
              day: '2-digit',
              month: 'short',
              hour: '2-digit',
              minute: '2-digit',
            })
          }

          return date.toLocaleDateString([], {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
          })
        },
      },
    },
    yaxis: {
      labels: {
        style: {
          colors: textColor,
        },
        formatter: (val: number) => {
          return val.toLocaleString()
        },
      },
    },
    tooltip: {
      shared: false,
      intersect: false,
      theme: isDark.value ? 'dark' : 'light',
      x: {
        format: store.dashboard.timelineFormat === 'hour' ? 'dd MMM HH:mm' : 'dd MMM yyyy',
      },
      y: {
        formatter: (val: number) => {
          return val.toLocaleString()
        },
      },
      style: {
        fontSize: '12px',
      },
    },
    markers: {
      size: 0,
      hover: {
        size: 5,
      },
    },
    legend: {
      show: false,
    },
  }
})
</script>
