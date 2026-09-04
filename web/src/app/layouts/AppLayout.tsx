import { Suspense } from 'react'
import { Outlet } from 'react-router'
import {
  ArrowRightLeft,
  BarChart3,
  BookOpenCheck,
  Bot,
  LayoutDashboard,
  Landmark,
  LogOut,
  Menu,
  Repeat,
  ScrollText,
  ShieldCheck,
  Sparkles,
  Tags,
  TrendingUp,
  Users,
  Wallet,
  Zap,
} from 'lucide-react'
import { TenantSelector } from '@/modules/auth/components/TenantSelector'
import { AssistantWidget } from '@/modules/assistant/components/AssistantWidget'
import { useSessionStore } from '@/shared/stores/session.store'
import { useTenantContextStore } from '@/shared/stores/tenant.store'
import { useUiStore } from '@/shared/stores/ui.store'
import { Permission } from '@/shared/constants/permissions'
import { usePermissions } from '@/shared/hooks/usePermissions'
import { useLogout } from '@/modules/auth/hooks/useAuth'
import {
  Avatar,
  Container,
  Dropdown,
  DropdownItem,
  DropdownSeparator,
  Loading,
  Sidebar,
  SidebarGroup,
  SidebarItem,
  ThemeToggle,
  Topbar,
} from '@/shared/design-system'
import { cn } from '@/shared/utils/cn'

function Brand() {
  const tenant = useSessionStore((state) => state.tenant)
  const isMaster = useSessionStore((state) => state.isMaster)
  const availableTenants = useSessionStore((state) => state.availableTenants)
  const selectedTenantId = useTenantContextStore((state) => state.selectedTenantId)

  const activeName = isMaster
    ? (availableTenants.find((item) => item.id === selectedTenantId)?.name ?? tenant?.name)
    : tenant?.name

  return (
    <div className="flex items-center gap-2.5 px-1">
      <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary text-primary-foreground shadow-card">
        <Zap className="size-4.5" aria-hidden="true" />
      </span>
      <span className="min-w-0">
        <span className="block text-sm leading-tight font-semibold text-foreground">Nox</span>
        <span className="block truncate text-xs text-muted">{activeName}</span>
      </span>
    </div>
  )
}

function SidebarNavigation({ onNavigate }: { onNavigate?: () => void }) {
  const { can } = usePermissions()

  return (
    <Sidebar header={<Brand />}>
      <SidebarGroup label="Geral">
        <SidebarItem to="/dashboard" icon={LayoutDashboard} label="Dashboard" onNavigate={onNavigate} />
        {can(Permission.ASSISTANT_VIEW) && (
          <SidebarItem to="/assistant" icon={Sparkles} label="Assistente de IA" onNavigate={onNavigate} />
        )}
      </SidebarGroup>

      <SidebarGroup label="Financeiro">
        {can(Permission.CASH_FLOW_VIEW) && (
          <SidebarItem to="/cash-flow/realized" icon={TrendingUp} label="Fluxo realizado" onNavigate={onNavigate} />
        )}
        {can(Permission.CASH_FLOW_VIEW) && (
          <SidebarItem to="/cash-flow/projected" icon={BarChart3} label="Fluxo projetado" onNavigate={onNavigate} />
        )}
        {can(Permission.ACCOUNTS_VIEW) && (
          <SidebarItem to="/accounts" icon={Wallet} label="Contas a pagar/receber" onNavigate={onNavigate} />
        )}
        {can(Permission.RECURRENCES_VIEW) && (
          <SidebarItem to="/recurrences" icon={Repeat} label="Recorrências" onNavigate={onNavigate} />
        )}
        {can(Permission.TRANSFERS_VIEW) && (
          <SidebarItem to="/transfers" icon={ArrowRightLeft} label="Transferências" onNavigate={onNavigate} />
        )}
        {can(Permission.RECONCILIATION_VIEW) && (
          <SidebarItem to="/reconciliation" icon={BookOpenCheck} label="Conciliação" onNavigate={onNavigate} />
        )}
        {can(Permission.REPORTS_VIEW) && (
          <SidebarItem to="/reports" icon={BarChart3} label="Relatórios" onNavigate={onNavigate} />
        )}
      </SidebarGroup>

      <SidebarGroup label="Cadastros">
        {can(Permission.COST_CENTERS_VIEW) && (
          <SidebarItem to="/cost-centers" icon={Landmark} label="Centros de custo" onNavigate={onNavigate} />
        )}
        {can(Permission.CATEGORIES_VIEW) && (
          <SidebarItem to="/categories" icon={Tags} label="Categorias" onNavigate={onNavigate} />
        )}
      </SidebarGroup>

      <SidebarGroup label="Gestão">
        {can(Permission.USER_READ) && (
          <SidebarItem to="/users" icon={Users} label="Usuários" onNavigate={onNavigate} />
        )}
        {can(Permission.ROLE_READ) && (
          <SidebarItem to="/roles" icon={ShieldCheck} label="Perfis de acesso" onNavigate={onNavigate} />
        )}
        {can(Permission.AUDIT_VIEW) && (
          <SidebarItem to="/audit" icon={ScrollText} label="Auditoria" onNavigate={onNavigate} />
        )}
        {can(Permission.ASSISTANT_CONFIGURE) && (
          <SidebarItem to="/settings/ai" icon={Bot} label="Inteligência Artificial" onNavigate={onNavigate} />
        )}
      </SidebarGroup>

    </Sidebar>
  )
}

function UserMenu() {
  const user = useSessionStore((state) => state.user)
  const logout = useLogout()

  if (!user) return null

  return (
    <Dropdown
      label="Menu do usuário"
      trigger={
        <span className="flex items-center gap-2.5 rounded-lg p-1.5 transition-colors hover:bg-surface-2">
          <Avatar name={user.name} size="sm" />
          <span className="hidden text-left sm:block">
            <span className="block max-w-36 truncate text-[13px] leading-tight font-medium text-foreground">
              {user.name}
            </span>
            <span className="block max-w-36 truncate text-xs text-muted">{user.email}</span>
          </span>
        </span>
      }
    >
      <div className="px-3 py-2 sm:hidden">
        <p className="truncate text-sm font-medium text-foreground">{user.name}</p>
        <p className="truncate text-xs text-muted">{user.email}</p>
      </div>
      <DropdownSeparator />
      <DropdownItem icon={LogOut} danger onSelect={() => logout.mutate()}>
        Sair da conta
      </DropdownItem>
    </Dropdown>
  )
}

export function AppLayout() {
  const sidebarOpen = useUiStore((state) => state.sidebarOpen)
  const closeSidebar = useUiStore((state) => state.closeSidebar)
  const openSidebar = useUiStore((state) => state.openSidebar)

  return (
    <div className="app-viewport-height flex overflow-hidden">
      <div className="z-20 hidden h-full shrink-0 overflow-hidden shadow-card lg:block">
        <SidebarNavigation />
      </div>

      {sidebarOpen && (
        <div
          className="animate-fade-in fixed inset-0 z-40 bg-overlay lg:hidden"
          onClick={closeSidebar}
          aria-hidden="true"
        />
      )}
      <div
        className={cn(
          'fixed inset-y-0 left-0 z-50 shadow-pop transition-transform duration-200 lg:hidden',
          sidebarOpen ? 'translate-x-0' : '-translate-x-full',
        )}
      >
        <SidebarNavigation onNavigate={closeSidebar} />
      </div>

      <div className="flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden">
        <Topbar>
          <button
            type="button"
            onClick={openSidebar}
            aria-label="Abrir menu"
            className="flex size-9 items-center justify-center rounded-lg text-muted transition-colors hover:bg-surface-2 hover:text-foreground lg:hidden"
          >
            <Menu className="size-5" />
          </button>
          <div className="ml-auto flex items-center gap-1.5">
            <TenantSelector />
            <ThemeToggle />
            <UserMenu />
          </div>
        </Topbar>

        <main className="min-h-0 flex-1 overflow-y-auto">
          <Container className="pt-2">
            <Suspense fallback={<Loading />}>
              <Outlet />
            </Suspense>
          </Container>
        </main>
      </div>

      <AssistantWidget />
    </div>
  )
}
