export type TimelineFormat = 'hour' | 'day'

export interface TreeFile {
  id: string
  name: string
  isValid: boolean
  isEmpty: boolean
  isTooLarge: boolean
  canDelete: boolean
  isDeletable: boolean
  canDownload: boolean
  isDownloadable: boolean
  size: number
}

export interface TreeFolder {
  name: string
  folders?: TreeFolder[]
  files?: TreeFile[]
}

export interface TreeRootNode {
  name: string
  folders?: TreeFolder[]
  files?: TreeFile[]
}

export interface LogEntry {
  timestamp: string
  normalizedTimestamp: string
  level: string
  channel: string
  message: string
  file: string
  sourceId?: string
  sql?: string
  context?: Record<string, unknown>
}

export interface AiChat {
  name: string
  url: string
  hasPrompt: boolean
}

export interface RegexTemplate {
  label: string
  value: string
}

export interface SourceInfo {
  id: string
  name: string
  path: string
  total: number
  size?: number
  isValid: boolean
  isEmpty: boolean
  isTooLarge: boolean
  canDelete: boolean
  isDeletable: boolean
  canDownload: boolean
  isDownloadable: boolean
  calculatedAt?: string
}

export interface DashboardStats {
  totalFiles: number
  totalEntries: number
  totalSize: number
  calculatedAt?: string
  levels: Record<string, number>
  channels: Record<string, number>
  timeline: Record<string, number>
  sources: SourceInfo[]
}

export interface LogStats {
  total: number
  size: number
  levels: Record<string, number>
  channels: Record<string, number>
  updatedAt?: string
  calculatedAt?: string
}

export interface AppConfig {
  apiPrefix: string
  structure: TreeRootNode[]
  dashboardPageStatisticEnabled: boolean
  logPageStatisticEnabled: boolean
  logPageAutoRefreshEnabled: boolean
  logPageAutoRefreshInterval: number | null
  logPageAutoRefreshShowCountdown: boolean
  logPageLimit: number
  dashboardPageAutoRefreshEnabled: boolean
  dashboardPageAutoRefreshInterval: number | null
  dashboardPageAutoRefreshShowCountdown: boolean
  liveLogPageEnabled: boolean
  liveLogPageInterval: number | null
  liveSelectedLevels: string[]
  aiButtonLevels: string[]
  aiChats: AiChat[]
  regexTemplates: RegexTemplate[]
}

export interface ServerConfig {
  dashboardPageStatisticEnabled: boolean
  logPageStatisticEnabled: boolean
  sourceAllowDelete: boolean
  sourceAllowDownload: boolean
  apiPrefix: string
  logPageAutoRefreshEnabled: boolean
  logPageAutoRefreshInterval: number | null
  logPageAutoRefreshShowCountdown: boolean
  logPageLimit: number
  dashboardPageAutoRefreshEnabled: boolean
  dashboardPageAutoRefreshInterval: number | null
  dashboardPageAutoRefreshShowCountdown: boolean
  liveLogPageEnabled: boolean
  liveLogPageInterval: number | null
  liveSelectedLevels: string[]
  aiButtonLevels: string[]
  aiChats: AiChat[]
  regexTemplates: RegexTemplate[]
}
