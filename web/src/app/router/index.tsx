import { lazy } from 'react'
import { createBrowserRouter, Navigate } from 'react-router'
import { AuthGuard } from '@/app/guards/AuthGuard'
import { GuestGuard } from '@/app/guards/GuestGuard'
import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { AppLayout } from '@/app/layouts/AppLayout'
import { AuthLayout } from '@/app/layouts/AuthLayout'
import { Permission } from '@/shared/constants/permissions'
import { NotFoundPage } from './NotFoundPage'

const LoginPage = lazy(() => import('@/modules/auth/pages/LoginPage'))
const DashboardPage = lazy(() => import('@/modules/dashboard/pages/DashboardPage'))
const UsersListPage = lazy(() => import('@/modules/users/pages/UsersListPage'))
const UserCreatePage = lazy(() => import('@/modules/users/pages/UserCreatePage'))
const UserEditPage = lazy(() => import('@/modules/users/pages/UserEditPage'))
const RolesListPage = lazy(() => import('@/modules/roles/pages/RolesListPage'))
const RoleCreatePage = lazy(() => import('@/modules/roles/pages/RoleCreatePage'))
const RoleEditPage = lazy(() => import('@/modules/roles/pages/RoleEditPage'))

const CostCentersListPage = lazy(() => import('@/modules/cost-centers/pages/CostCentersListPage'))
const CostCenterCreatePage = lazy(() => import('@/modules/cost-centers/pages/CostCenterCreatePage'))
const CostCenterEditPage = lazy(() => import('@/modules/cost-centers/pages/CostCenterEditPage'))

const BankAccountsListPage = lazy(() => import('@/modules/bank-accounts/pages/BankAccountsListPage'))
const BankAccountCreatePage = lazy(() => import('@/modules/bank-accounts/pages/BankAccountCreatePage'))
const BankAccountEditPage = lazy(() => import('@/modules/bank-accounts/pages/BankAccountEditPage'))

const CompaniesListPage = lazy(() => import('@/modules/companies/pages/CompaniesListPage'))
const CompanyCreatePage = lazy(() => import('@/modules/companies/pages/CompanyCreatePage'))
const CompanyEditPage = lazy(() => import('@/modules/companies/pages/CompanyEditPage'))

const CreditCardsListPage = lazy(() => import('@/modules/credit-cards/pages/CreditCardsListPage'))
const CreditCardCreatePage = lazy(() => import('@/modules/credit-cards/pages/CreditCardCreatePage'))
const CreditCardEditPage = lazy(() => import('@/modules/credit-cards/pages/CreditCardEditPage'))
const CreditCardDetailPage = lazy(() => import('@/modules/credit-cards/pages/CreditCardDetailPage'))

const CategoriesListPage = lazy(() => import('@/modules/categories/pages/CategoriesListPage'))
const CategoryCreatePage = lazy(() => import('@/modules/categories/pages/CategoryCreatePage'))
const CategoryEditPage = lazy(() => import('@/modules/categories/pages/CategoryEditPage'))

const AccountsListPage = lazy(() => import('@/modules/accounts/pages/AccountsListPage'))
const AccountCreatePage = lazy(() => import('@/modules/accounts/pages/AccountCreatePage'))
const AccountEditPage = lazy(() => import('@/modules/accounts/pages/AccountEditPage'))

const RecurrencesListPage = lazy(() => import('@/modules/recurrences/pages/RecurrencesListPage'))
const RecurrenceCreatePage = lazy(() => import('@/modules/recurrences/pages/RecurrenceCreatePage'))
const RecurrenceEditPage = lazy(() => import('@/modules/recurrences/pages/RecurrenceEditPage'))

const TransfersListPage = lazy(() => import('@/modules/transfers/pages/TransfersListPage'))
const TransferCreatePage = lazy(() => import('@/modules/transfers/pages/TransferCreatePage'))

const CashFlowRealizedPage = lazy(() => import('@/modules/cash-flow/pages/CashFlowRealizedPage'))
const CashFlowProjectedPage = lazy(() => import('@/modules/cash-flow/pages/CashFlowProjectedPage'))

const ReconciliationPage = lazy(() => import('@/modules/reconciliation/pages/ReconciliationPage'))

const ReportsPage = lazy(() => import('@/modules/reports/pages/ReportsPage'))

const AuditPage = lazy(() => import('@/modules/audit/pages/AuditPage'))

const AssistantPage = lazy(() => import('@/modules/assistant/pages/AssistantPage'))
const AiSettingsPage = lazy(() => import('@/modules/assistant/pages/AiSettingsPage'))

export const router = createBrowserRouter([
  {
    element: <GuestGuard />,
    children: [
      {
        element: <AuthLayout />,
        children: [
          { path: '/auth/login', element: <LoginPage /> },
        ],
      },
    ],
  },
  {
    element: <AuthGuard />,
    children: [
      {
        element: <AppLayout />,
        children: [
          { path: '/', element: <Navigate to="/dashboard" replace /> },
          { path: '/dashboard', element: <DashboardPage /> },
          {
            path: '/users',
            element: (
              <PermissionGuard permission={Permission.USER_READ}>
                <UsersListPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/users/create',
            element: (
              <PermissionGuard permission={Permission.USER_CREATE}>
                <UserCreatePage />
              </PermissionGuard>
            ),
          },
          {
            path: '/users/:id/edit',
            element: (
              <PermissionGuard permission={Permission.USER_UPDATE}>
                <UserEditPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/roles',
            element: (
              <PermissionGuard permission={Permission.ROLE_READ}>
                <RolesListPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/roles/create',
            element: (
              <PermissionGuard permission={Permission.ROLE_CREATE}>
                <RoleCreatePage />
              </PermissionGuard>
            ),
          },
          {
            path: '/roles/:id/edit',
            element: (
              <PermissionGuard permission={Permission.ROLE_UPDATE}>
                <RoleEditPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/bank-accounts',
            element: (
              <PermissionGuard permission={Permission.BANK_ACCOUNTS_VIEW}>
                <BankAccountsListPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/bank-accounts/create',
            element: (
              <PermissionGuard permission={Permission.BANK_ACCOUNTS_CREATE}>
                <BankAccountCreatePage />
              </PermissionGuard>
            ),
          },
          {
            path: '/bank-accounts/:id/edit',
            element: (
              <PermissionGuard permission={Permission.BANK_ACCOUNTS_UPDATE}>
                <BankAccountEditPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/cost-centers',
            element: (
              <PermissionGuard permission={Permission.COST_CENTERS_VIEW}>
                <CostCentersListPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/cost-centers/create',
            element: (
              <PermissionGuard permission={Permission.COST_CENTERS_CREATE}>
                <CostCenterCreatePage />
              </PermissionGuard>
            ),
          },
          {
            path: '/cost-centers/:id/edit',
            element: (
              <PermissionGuard permission={Permission.COST_CENTERS_UPDATE}>
                <CostCenterEditPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/companies',
            element: (
              <PermissionGuard permission={Permission.COMPANIES_VIEW}>
                <CompaniesListPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/companies/create',
            element: (
              <PermissionGuard permission={Permission.COMPANIES_CREATE}>
                <CompanyCreatePage />
              </PermissionGuard>
            ),
          },
          {
            path: '/companies/:id/edit',
            element: (
              <PermissionGuard permission={Permission.COMPANIES_UPDATE}>
                <CompanyEditPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/credit-cards',
            element: (
              <PermissionGuard permission={Permission.CREDIT_CARDS_VIEW}>
                <CreditCardsListPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/credit-cards/create',
            element: (
              <PermissionGuard permission={Permission.CREDIT_CARDS_CREATE}>
                <CreditCardCreatePage />
              </PermissionGuard>
            ),
          },
          {
            path: '/credit-cards/:id/edit',
            element: (
              <PermissionGuard permission={Permission.CREDIT_CARDS_UPDATE}>
                <CreditCardEditPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/credit-cards/:id',
            element: (
              <PermissionGuard permission={Permission.CREDIT_CARDS_VIEW}>
                <CreditCardDetailPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/categories',
            element: (
              <PermissionGuard permission={Permission.CATEGORIES_VIEW}>
                <CategoriesListPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/categories/create',
            element: (
              <PermissionGuard permission={Permission.CATEGORIES_CREATE}>
                <CategoryCreatePage />
              </PermissionGuard>
            ),
          },
          {
            path: '/categories/:id/edit',
            element: (
              <PermissionGuard permission={Permission.CATEGORIES_UPDATE}>
                <CategoryEditPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/accounts',
            element: (
              <PermissionGuard permission={Permission.ACCOUNTS_VIEW}>
                <AccountsListPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/accounts/create',
            element: (
              <PermissionGuard permission={Permission.ACCOUNTS_CREATE}>
                <AccountCreatePage />
              </PermissionGuard>
            ),
          },
          {
            path: '/accounts/:id/edit',
            element: (
              <PermissionGuard permission={Permission.ACCOUNTS_UPDATE}>
                <AccountEditPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/recurrences',
            element: (
              <PermissionGuard permission={Permission.RECURRENCES_VIEW}>
                <RecurrencesListPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/recurrences/create',
            element: (
              <PermissionGuard permission={Permission.RECURRENCES_CREATE}>
                <RecurrenceCreatePage />
              </PermissionGuard>
            ),
          },
          {
            path: '/recurrences/:id/edit',
            element: (
              <PermissionGuard permission={Permission.RECURRENCES_UPDATE}>
                <RecurrenceEditPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/transfers',
            element: (
              <PermissionGuard permission={Permission.TRANSFERS_VIEW}>
                <TransfersListPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/transfers/create',
            element: (
              <PermissionGuard permission={Permission.TRANSFERS_CREATE}>
                <TransferCreatePage />
              </PermissionGuard>
            ),
          },
          {
            path: '/cash-flow/realized',
            element: (
              <PermissionGuard permission={Permission.CASH_FLOW_VIEW}>
                <CashFlowRealizedPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/cash-flow/projected',
            element: (
              <PermissionGuard permission={Permission.CASH_FLOW_VIEW}>
                <CashFlowProjectedPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/reconciliation',
            element: (
              <PermissionGuard permission={Permission.RECONCILIATION_VIEW}>
                <ReconciliationPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/reports',
            element: (
              <PermissionGuard permission={Permission.REPORTS_VIEW}>
                <ReportsPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/audit',
            element: (
              <PermissionGuard permission={Permission.AUDIT_VIEW}>
                <AuditPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/settings/ai',
            element: (
              <PermissionGuard permission={Permission.ASSISTANT_CONFIGURE}>
                <AiSettingsPage />
              </PermissionGuard>
            ),
          },
        ],
      },
      {
        path: '/assistant/:id?',
        element: (
          <PermissionGuard permission={Permission.ASSISTANT_VIEW}>
            <AssistantPage />
          </PermissionGuard>
        ),
      },
    ],
  },
  { path: '*', element: <NotFoundPage /> },
])
