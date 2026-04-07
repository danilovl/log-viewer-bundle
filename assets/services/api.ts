import axios from 'axios'
import { useToastStore } from '@/stores/useToastStore'
import { useI18n } from '@/i18n/useI18n'
import type { DashboardStats, LogEntry, LogStats, ServerConfig, TimelineFormat, TreeRootNode } from '@/types'

let apiPrefix = ''

export function setApiPrefix(prefix: string): void {
  apiPrefix = prefix
}

axios.interceptors.response.use(
  (response) => {
    return response
  },
  async (error) => {
    const toastStore = useToastStore()
    const { t } = useI18n()

    let messageKey = error.response?.data?.message
    let message = messageKey ? t(messageKey) : error.message || t('unknownError')

    if (error.response?.data instanceof Blob) {
      const text = await error.response.data.text()
      try {
        const data = JSON.parse(text)
        if (data.message) {
          message = t(data.message)
        }
      } catch {
        /*  empty  */
      }
    }

    if (error.response?.status === 404) {
      message = t('sourceNotFound')
    } else if (error.response?.status === 500) {
      message = 'Internal  server  error'
    }

    toastStore.error(message)

    return Promise.reject(error)
  },
)

export async function fetchConfig(): Promise<ServerConfig> {
  const response = await axios.get(`${apiPrefix}/config`)

  return response.data
}

export async function fetchStructure(): Promise<TreeRootNode[]> {
  const response = await axios.get(`${apiPrefix}/structure`)

  return response.data
}

export async function fetchDashboardStats(timelineFormat?: TimelineFormat): Promise<{
  data: DashboardStats
  duration: number
}> {
  const startTime = performance.now()
  const params: Record<string, string> = {}
  if (timelineFormat) {
    params.timelineFormat = timelineFormat
  }
  const response = await axios.get(`${apiPrefix}/dashboard-stats`, { params })
  const duration = Math.round(performance.now() - startTime)

  return {
    data: response.data,
    duration,
  }
}

export async function fetchLogStats(
  sourceId: string,
  level: string,
  channel: string,
  search: string,
  dateFrom: string,
  dateTo: string,
  searchRegex: boolean,
  searchCaseSensitive: boolean,
): Promise<{
  data: LogStats
  path: string
  parserType: string
  host: string
  canDelete: boolean
  canDownload: boolean
  duration: number
}> {
  const startTime = performance.now()
  const params: Record<string, string> = { sourceId }

  if (level) {
    params.level = level
  }
  if (channel) {
    params.channel = channel
  }
  if (search) {
    params.search = search
  }
  if (searchRegex) {
    params.searchRegex = '1'
  }
  if (searchCaseSensitive) {
    params.searchCaseSensitive = '1'
  }
  if (dateFrom) {
    params.dateFrom = dateFrom
  }
  if (dateTo) {
    params.dateTo = dateTo
  }

  const response = await axios.get(`${apiPrefix}/stats`, { params })
  const duration = Math.round(performance.now() - startTime)

  return {
    data: response.data.stats,
    path: response.data.path,
    parserType: response.data.parserType,
    host: response.data.host,
    canDelete: response.data.canDelete,
    canDownload: response.data.canDownload,
    duration,
  }
}

export async function fetchLogEntries(
  sourceId: string,
  limit: number,
  offset: number,
  sortDir: string,
  level: string,
  channel: string,
  search: string,
  dateFrom: string,
  dateTo: string,
  searchRegex: boolean,
  searchCaseSensitive: boolean,
): Promise<{
  data: LogEntry[]
  parserType: string
  host: string
  path: string
  size: number
  canDelete: boolean
  canDownload: boolean
  duration: number
}> {
  const startTime = performance.now()
  const params: Record<string, string | number> = {
    sourceId,
    limit,
    offset,
    sortDir,
  }

  if (level) {
    params.level = level
  }
  if (channel) {
    params.channel = channel
  }
  if (search) {
    params.search = search
  }
  if (searchRegex) {
    params.searchRegex = '1'
  }
  if (searchCaseSensitive) {
    params.searchCaseSensitive = '1'
  }
  if (dateFrom) {
    params.dateFrom = dateFrom
  }
  if (dateTo) {
    params.dateTo = dateTo
  }

  const response = await axios.get(`${apiPrefix}/entries`, { params })
  const duration = Math.round(performance.now() - startTime)

  return {
    data: response.data.entries,
    parserType: response.data.parserType,
    host: response.data.host,
    path: response.data.path,
    size: response.data.size,
    canDelete: response.data.canDelete,
    canDownload: response.data.canDownload,
    duration,
  }
}

export async function fetchLogEntriesCount(
  sourceId: string,
  level: string,
  channel: string,
  search: string,
  dateFrom: string,
  dateTo: string,
  searchRegex: boolean,
  searchCaseSensitive: boolean,
): Promise<{
  totalCount: number
  duration: number
}> {
  const startTime = performance.now()
  const params: Record<string, string> = { sourceId }

  if (level) {
    params.level = level
  }
  if (channel) {
    params.channel = channel
  }
  if (search) {
    params.search = search
  }
  if (searchRegex) {
    params.searchRegex = '1'
  }
  if (searchCaseSensitive) {
    params.searchCaseSensitive = '1'
  }
  if (dateFrom) {
    params.dateFrom = dateFrom
  }
  if (dateTo) {
    params.dateTo = dateTo
  }

  const response = await axios.get(`${apiPrefix}/entries-count`, { params })
  const duration = Math.round(performance.now() - startTime)

  return {
    totalCount: response.data.totalCount,
    duration,
  }
}

export async function fetchNewLogEntries(
  levels?: string[],
  sourceIds?: string[],
): Promise<{
  entries: LogEntry[]
  count: number
  duration: number
  calculatedAt: string
}> {
  const startTime = performance.now()
  const params: Record<string, string> = {}
  if (levels && levels.length > 0) {
    params.levels = levels.join(',')
  }
  if (sourceIds && sourceIds.length > 0) {
    params.sourceIds = sourceIds.join(',')
  }

  const response = await axios.get(`${apiPrefix}/entries/new`, { params })
  const duration = Math.round(performance.now() - startTime)

  return {
    ...response.data,
    duration,
  }
}

export async function deleteLogFile(sourceId: string): Promise<void> {
  await axios.delete(`${apiPrefix}/delete`, { params: { sourceId } })
}

export async function downloadLogFile(sourceId: string): Promise<Blob> {
  const response = await axios.get(`${apiPrefix}/download`, {
    params: { sourceId },
    responseType: 'blob',
  })

  return response.data
}

export async function fetchGlobalSearch(
  sourceIds: string[],
  limit: number,
  offset: number,
  sortDir: string,
  level: string,
  channel: string,
  search: string,
  dateFrom: string,
  dateTo: string,
  searchRegex: boolean,
  searchCaseSensitive: boolean,
): Promise<{
  entries: LogEntry[]
  count: number
  duration: number
}> {
  const startTime = performance.now()
  const params: Record<string, string | number> = {
    sourceId: sourceIds.join(','),
    limit,
    offset,
    sortDir,
  }

  if (level) {
    params.level = level
  }
  if (channel) {
    params.channel = channel
  }
  if (search) {
    params.search = search
  }
  if (searchRegex) {
    params.searchRegex = '1'
  }
  if (searchCaseSensitive) {
    params.searchCaseSensitive = '1'
  }
  if (dateFrom) {
    params.dateFrom = dateFrom
  }
  if (dateTo) {
    params.dateTo = dateTo
  }

  const response = await axios.get(`${apiPrefix}/global-search`, { params })
  const duration = Math.round(performance.now() - startTime)

  return {
    entries: response.data.entries,
    count: response.data.count,
    duration,
  }
}
