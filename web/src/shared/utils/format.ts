/** Formato único de data no sistema: DD/MM/YYYY */
const numericDateFormatter = new Intl.DateTimeFormat('pt-BR', {
  day: '2-digit',
  month: '2-digit',
  year: 'numeric',
})
const dateTimeFormatter = new Intl.DateTimeFormat('pt-BR', {
  day: '2-digit',
  month: '2-digit',
  year: 'numeric',
  hour: '2-digit',
  minute: '2-digit',
})
const relativeFormatter = new Intl.RelativeTimeFormat('pt-BR', { numeric: 'auto' })

/**
 * Converte uma data para o formato local ISO (YYYY-MM-DD).
 * `Date#toISOString` usa UTC e, em fusos atrás do UTC (ex.: Brasil),
 * produziria a data do dia anterior entre 00:00 e 03:00.
 */
export function toLocalIsoDate(date: Date = new Date()): string {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')

  return `${year}-${month}-${day}`
}

/**
 * Interpreta strings de data:
 * - "YYYY-MM-DD" (data pura) → data local, evitando o deslocamento de fuso.
 * - ISO com horário/timezone → `new Date` normalmente.
 */
function parseDateInput(value: string): Date {
  const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value)

  if (match) {
    return new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]))
  }

  return new Date(value)
}

export function formatDate(value: string | null | undefined): string {
  if (!value) return '—'

  return numericDateFormatter.format(parseDateInput(value))
}

/** Alias de formatDate — mantido por compatibilidade. Sempre DD/MM/YYYY. */
export function formatShortDate(value: string | null | undefined): string {
  return formatDate(value)
}

export function formatDateTime(value: string | null | undefined): string {
  if (!value) return '—'

  return dateTimeFormatter.format(new Date(value))
}

const RELATIVE_STEPS: Array<[Intl.RelativeTimeFormatUnit, number]> = [
  ['year', 1000 * 60 * 60 * 24 * 365],
  ['month', 1000 * 60 * 60 * 24 * 30],
  ['day', 1000 * 60 * 60 * 24],
  ['hour', 1000 * 60 * 60],
  ['minute', 1000 * 60],
]

export function formatRelative(value: string | null | undefined): string {
  if (!value) return '—'

  const diff = new Date(value).getTime() - Date.now()

  for (const [unit, ms] of RELATIVE_STEPS) {
    if (Math.abs(diff) >= ms) {
      return relativeFormatter.format(Math.round(diff / ms), unit)
    }
  }

  return 'agora mesmo'
}

export function getInitials(name: string): string {
  return name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() ?? '')
    .join('')
}

const currencyFormatter = new Intl.NumberFormat('pt-BR', {
  style: 'currency',
  currency: 'BRL',
})

export function formatCurrency(value: string | number | null | undefined): string {
  if (value === null || value === undefined || value === '') return '—'

  return currencyFormatter.format(Number(value))
}

export function formatBytes(value: number | null | undefined): string {
  if (value === null || value === undefined || Number.isNaN(Number(value))) return '—'

  const bytes = Number(value)

  if (bytes === 0) return '0 B'

  const units = ['B', 'KB', 'MB', 'GB', 'TB']
  const exponent = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1)
  const amount = bytes / 1024 ** exponent

  return `${amount.toLocaleString('pt-BR', { maximumFractionDigits: 1 })} ${units[exponent]}`
}
