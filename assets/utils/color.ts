import { hexToRgb } from '@/utils/format'
import { errorLevels } from '@/utils/constants'

export const palette = ['#6366f1', '#10b981', '#f59e0b', '#ef4444', '#3b82f6', '#8b5cf6', '#ec4899', '#f97316']

export function levelColor(level: string): string {
  const upperLevel = level.toUpperCase()

  if (errorLevels.includes(upperLevel)) {
    return '#ef4444'
  }
  if (upperLevel === 'WARNING') {
    return '#f59e0b'
  }
  if (upperLevel === 'NOTICE') {
    return '#3b82f6'
  }
  if (upperLevel === 'INFO') {
    return '#10b981'
  }

  return '#64748b'
}

export function iconBg(color: string): string {
  const { r, g, b } = hexToRgb(color)

  return `rgba(${r},  ${g},  ${b},  0.1)`
}
