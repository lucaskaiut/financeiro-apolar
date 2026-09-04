export interface DateRange {
  from: string
  to: string
}

export function toIsoDate(date: Date): string {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')

  return `${year}-${month}-${day}`
}

export function parseIsoDate(value: string): Date | null {
  const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value)

  if (!match) {
    return null
  }

  return new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]))
}

export function formatDisplayDate(iso: string): string {
  const date = parseIsoDate(iso)

  if (!date) {
    return ''
  }

  const day = String(date.getDate()).padStart(2, '0')
  const month = String(date.getMonth() + 1).padStart(2, '0')

  return `${day}/${month}/${date.getFullYear()}`
}

export function parseDisplayDate(value: string): string | null {
  const match = /^(\d{2})\/(\d{2})\/(\d{4})$/.exec(value.trim())

  if (!match) {
    return null
  }

  const day = Number(match[1])
  const month = Number(match[2])
  const year = Number(match[3])
  const date = new Date(year, month - 1, day)

  if (date.getFullYear() !== year || date.getMonth() !== month - 1 || date.getDate() !== day) {
    return null
  }

  return toIsoDate(date)
}

export function maskDateInput(input: string): string {
  const digits = input.replace(/\D/g, '').slice(0, 8)

  if (digits.length <= 2) {
    return digits
  }

  if (digits.length <= 4) {
    return `${digits.slice(0, 2)}/${digits.slice(2)}`
  }

  return `${digits.slice(0, 2)}/${digits.slice(2, 4)}/${digits.slice(4)}`
}

export function isSameDay(a: Date, b: Date): boolean {
  return a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate()
}

export function getCalendarWeeks(viewDate: Date): Date[][] {
  const year = viewDate.getFullYear()
  const month = viewDate.getMonth()
  const firstOfMonth = new Date(year, month, 1)

  let startOffset = firstOfMonth.getDay() - 1

  if (startOffset < 0) {
    startOffset = 6
  }

  const startDate = addDays(firstOfMonth, -startOffset)
  const weeks: Date[][] = []

  for (let week = 0; week < 6; week++) {
    const days: Date[] = []

    for (let day = 0; day < 7; day++) {
      days.push(addDays(startDate, week * 7 + day))
    }

    weeks.push(days)
  }

  return weeks
}

export function isDateInRange(date: Date, min?: string, max?: string): boolean {
  const iso = toIsoDate(date)

  if (min && iso < min) {
    return false
  }

  if (max && iso > max) {
    return false
  }

  return true
}

export function addDays(date: Date, amount: number): Date {
  const copy = new Date(date)

  copy.setDate(copy.getDate() + amount)

  return copy
}

export function startOfWeek(date: Date): Date {
  const copy = new Date(date)
  const day = copy.getDay()

  copy.setDate(copy.getDate() + (day === 0 ? -6 : 1 - day))

  return copy
}

export function startOfMonth(date: Date): Date {
  return new Date(date.getFullYear(), date.getMonth(), 1)
}

export function endOfMonth(date: Date): Date {
  return new Date(date.getFullYear(), date.getMonth() + 1, 0)
}

interface RangePreset extends DateRange {
  label: string
  single?: boolean
}

export function buildDateRangePresets(): RangePreset[] {
  const today = new Date()
  const yesterday = addDays(today, -1)
  const tomorrow = addDays(today, 1)

  const monday = startOfWeek(today)
  const sunday = addDays(monday, 6)
  const prevMonday = addDays(monday, -7)
  const prevSunday = addDays(monday, -1)
  const nextMonday = addDays(monday, 7)
  const nextSunday = addDays(monday, 13)

  const monthStart = startOfMonth(today)
  const monthEnd = endOfMonth(today)
  const prevMonthEnd = addDays(monthStart, -1)
  const prevMonthStart = startOfMonth(prevMonthEnd)

  return [
    { label: 'Hoje', from: toIsoDate(today), to: toIsoDate(today), single: true },
    { label: 'Ontem', from: toIsoDate(yesterday), to: toIsoDate(yesterday), single: true },
    { label: 'Amanhã', from: toIsoDate(tomorrow), to: toIsoDate(tomorrow), single: true },
    { label: 'Últimos 7 dias', from: toIsoDate(addDays(today, -6)), to: toIsoDate(today) },
    { label: 'Próximos 7 dias', from: toIsoDate(today), to: toIsoDate(addDays(today, 6)) },
    { label: 'Esta semana', from: toIsoDate(monday), to: toIsoDate(sunday) },
    { label: 'Semana passada', from: toIsoDate(prevMonday), to: toIsoDate(prevSunday) },
    { label: 'Próxima semana', from: toIsoDate(nextMonday), to: toIsoDate(nextSunday) },
    { label: 'Este mês', from: toIsoDate(monthStart), to: toIsoDate(monthEnd) },
    { label: 'Mês passado', from: toIsoDate(prevMonthStart), to: toIsoDate(prevMonthEnd) },
    { label: 'Últimos 30 dias', from: toIsoDate(addDays(today, -29)), to: toIsoDate(today) },
    { label: 'Últimos 90 dias', from: toIsoDate(addDays(today, -89)), to: toIsoDate(today) },
  ]
}
