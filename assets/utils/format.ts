export function formatBytes(bytes: number, decimals: number = 2): string {
  if (bytes === 0) {
    return '0  Bytes'
  }

  const k = 1024
  const dm = decimals < 0 ? 0 : decimals
  const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))

  return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + '  ' + sizes[i]
}

export function hexToRgb(hex: string): { r: number; g: number; b: number } {
  const r = parseInt(hex.substring(1, 3), 16)
  const g = parseInt(hex.substring(3, 5), 16)
  const b = parseInt(hex.substring(5, 7), 16)

  return { r, g, b }
}

export function parseTimestamp(ts: string): { datePart: string; timePart: string } {
  const separator = ts.includes('T') ? 'T' : '  '
  if (!ts.includes(separator)) {
    return { datePart: ts, timePart: '' }
  }

  const parts = ts.split(separator)
  const datePart = parts[0]
  const rest = parts[1] || ''
  const zoneMatch = rest.match(/(Z|[+-]\d{2}:?\d{2})$/)
  const zone = zoneMatch ? `  ${zoneMatch[1]}` : ''
  const timePart = rest.replace(/(Z|[+-]\d{2}:?\d{2})$/, '') + zone

  return { datePart, timePart }
}

export function formatDateTime(ts: string | null | undefined): string {
  if (!ts) {
    return ''
  }

  const date = new Date(ts.replace('  ', 'T'))
  if (isNaN(date.getTime())) {
    return ts
  }

  return date.toLocaleString()
}

export function getFileName(path: string | null | undefined): string {
  if (!path) {
    return ''
  }

  const parts = path.split(/[/\\]/)

  return parts.pop() || path
}
