import type { ReactNode } from 'react'
import { NavLink } from 'react-router'
import type { LucideIcon } from 'lucide-react'
import { cn } from '@/shared/utils/cn'
import { Tooltip } from './Tooltip'

export function Sidebar({
  header,
  footer,
  collapsed = false,
  children,
  className,
}: {
  header?: ReactNode
  footer?: ReactNode
  collapsed?: boolean
  children: ReactNode
  className?: string
}) {
  return (
    <aside
      className={cn(
        'flex h-full flex-col bg-surface transition-[width] duration-200 ease-out',
        collapsed ? 'w-20' : 'w-64',
        className,
      )}
    >
      {header && <div className={cn('pt-5 pb-2', collapsed ? 'px-2' : 'px-4')}>{header}</div>}
      <nav
        className={cn('flex-1 space-y-6 overflow-y-auto py-4', collapsed ? 'px-2' : 'px-3')}
        aria-label="Menu principal"
      >
        {children}
      </nav>
      {footer && <div className={cn('pb-4', collapsed ? 'px-2' : 'px-3')}>{footer}</div>}
    </aside>
  )
}

export function SidebarGroup({
  label,
  collapsed = false,
  children,
}: {
  label?: string
  collapsed?: boolean
  children: ReactNode
}) {
  return (
    <div>
      {label && !collapsed && (
        <p className="px-3 pb-2 text-[11px] font-semibold tracking-wider text-subtle uppercase">
          {label}
        </p>
      )}
      <div className="space-y-0.5">{children}</div>
    </div>
  )
}

export function SidebarItem({
  to,
  icon: Icon,
  label,
  collapsed = false,
  onNavigate,
}: {
  to: string
  icon: LucideIcon
  label: string
  collapsed?: boolean
  onNavigate?: () => void
}) {
  const link = (
    <NavLink
      to={to}
      onClick={onNavigate}
      aria-label={collapsed ? label : undefined}
      className={({ isActive }) =>
        cn(
          'flex h-9 items-center rounded-lg text-sm transition-colors',
          collapsed ? 'w-full justify-center px-0' : 'gap-3 px-3',
          isActive
            ? 'bg-primary-soft font-medium text-primary'
            : 'text-muted hover:bg-surface-2 hover:text-foreground',
        )
      }
    >
      <Icon className="size-4.5 shrink-0" aria-hidden="true" />
      {!collapsed && <span className="truncate">{label}</span>}
    </NavLink>
  )

  if (!collapsed) return link

  return (
    <Tooltip label={label} side="right" className="w-full">
      {link}
    </Tooltip>
  )
}
