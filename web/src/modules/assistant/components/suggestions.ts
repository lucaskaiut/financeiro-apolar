import {
  AlertTriangle,
  BookOpenCheck,
  CalendarClock,
  PieChart,
  TrendingUp,
  Wallet,
  type LucideIcon,
} from 'lucide-react'

export interface SuggestionCard {
  title: string
  description: string
  prompt: string
  icon: LucideIcon
  tint: 'primary' | 'success' | 'warning' | 'danger'
}

export const SUGGESTIONS: SuggestionCard[] = [
  {
    title: 'Qual meu saldo atual?',
    description: 'Veja seu saldo consolidado',
    prompt: 'Qual meu saldo atual?',
    icon: Wallet,
    tint: 'primary',
  },
  {
    title: 'Quais contas vencem hoje?',
    description: 'Confira os vencimentos de hoje',
    prompt: 'Quais contas vencem hoje?',
    icon: CalendarClock,
    tint: 'warning',
  },
  {
    title: 'Como está meu caixa?',
    description: 'Projeção dos próximos 30 dias',
    prompt: 'Como está meu caixa nos próximos 30 dias?',
    icon: TrendingUp,
    tint: 'success',
  },
  {
    title: 'Pendências de conciliação',
    description: 'Transações não conciliadas',
    prompt: 'Existem lançamentos pendentes de conciliação?',
    icon: BookOpenCheck,
    tint: 'primary',
  },
  {
    title: 'Maiores despesas do mês',
    description: 'Análise por categoria',
    prompt: 'Quais foram minhas maiores despesas este mês?',
    icon: PieChart,
    tint: 'danger',
  },
  {
    title: 'Contas atrasadas',
    description: 'Cobranças e atrasos',
    prompt: 'Quais contas estão atrasadas?',
    icon: AlertTriangle,
    tint: 'warning',
  },
]

export const TINT_CLASSES: Record<SuggestionCard['tint'], { icon: string; iconBg: string }> = {
  primary: { icon: 'text-primary', iconBg: 'bg-primary-soft' },
  success: { icon: 'text-success', iconBg: 'bg-success-soft' },
  warning: { icon: 'text-warning', iconBg: 'bg-warning-soft' },
  danger: { icon: 'text-danger', iconBg: 'bg-danger-soft' },
}
