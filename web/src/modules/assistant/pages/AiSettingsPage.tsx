import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { AlertCircle, CheckCircle2, CircleOff, Plug } from 'lucide-react'
import {
  Alert,
  Button,
  Card,
  CardContent,
  Form,
  Page,
  PageContent,
  PageHeader,
  Section,
  SwitchField,
  TextareaField,
  TextField,
} from '@/shared/design-system'
import { aiSettingsSchema, type AiSettingsFormValues } from '../schemas/ai-settings.schema'
import { useAiSettings, useTestConnection, useUpdateAiSettings } from '../hooks/useAssistant'
import type { ConnectionTestResult } from '../services/assistant.service'
import type { AiSettings } from '@/shared/types/models'
import { cn } from '@/shared/utils/cn'

function ConnectionStatusCard({ settings }: { settings?: AiSettings }) {
  if (!settings) return null

  const status: 'connected' | 'disconnected' | 'disabled' = !settings.enabled
    ? 'disabled'
    : settings.configured
      ? 'connected'
      : 'disconnected'

  const config = {
    connected: {
      icon: CheckCircle2,
      iconClass: 'bg-success-soft text-success',
      title: 'IA conectada',
      description: settings.model && settings.endpoint ? `Modelo: ${settings.model} · Endpoint: ${settings.endpoint}` : 'Integração ativa',
    },
    disconnected: {
      icon: AlertCircle,
      iconClass: 'bg-warning-soft text-warning',
      title: 'IA desconectada',
      description: 'Configuração necessária',
    },
    disabled: {
      icon: CircleOff,
      iconClass: 'bg-surface-2 text-muted',
      title: 'IA desabilitada',
      description: 'Ative a integração para usar o assistente',
    },
  }[status]

  const Icon = config.icon

  return (
    <Card>
      <CardContent>
        <div className="flex items-center gap-3">
          <span className={cn('flex size-10 shrink-0 items-center justify-center rounded-xl', config.iconClass)}>
            <Icon className="size-5" aria-hidden="true" />
          </span>
          <div className="min-w-0 flex-1">
            <p className="text-sm font-semibold text-foreground">{config.title}</p>
            <p className="truncate text-[13px] text-muted">{config.description}</p>
          </div>
          {status === 'connected' && (
            <span className="hidden items-center gap-1.5 rounded-full bg-success-soft px-2.5 py-1 text-xs font-medium text-success sm:flex">
              <span className="size-1.5 rounded-full bg-success" aria-hidden="true" />
              Ativa
            </span>
          )}
        </div>
      </CardContent>
    </Card>
  )
}

export default function AiSettingsPage() {
  const settingsQuery = useAiSettings()
  const updateSettings = useUpdateAiSettings()
  const testConnection = useTestConnection()
  const [testResult, setTestResult] = useState<ConnectionTestResult | null>(null)

  const settings = settingsQuery.data

  const form = useForm<AiSettingsFormValues>({
    resolver: zodResolver(aiSettingsSchema),
    values: {
      enabled: settings?.enabled ?? false,
      endpoint: settings?.endpoint ?? '',
      api_key: '',
      model: settings?.model ?? '',
      temperature: settings?.temperature?.toString() ?? '',
      max_tokens: settings?.max_tokens?.toString() ?? '',
      system_prompt: settings?.system_prompt ?? '',
    },
  })

  const handleSubmit = async (values: AiSettingsFormValues) => {
    setTestResult(null)
    await updateSettings.mutateAsync({
      enabled: values.enabled,
      endpoint: values.endpoint || null,
      api_key: values.api_key || null,
      model: values.model || null,
      temperature: values.temperature.trim() === '' ? null : Number(values.temperature),
      max_tokens: values.max_tokens.trim() === '' ? null : Number(values.max_tokens),
      system_prompt: values.system_prompt || null,
    })
  }

  const handleTest = async () => {
    setTestResult(null)
    const values = form.getValues()
    const result = await testConnection.mutateAsync({
      endpoint: values.endpoint || undefined,
      api_key: values.api_key || undefined,
      model: values.model || undefined,
    })
    setTestResult(result)
  }

  const variant = testResult ? (testResult.ok ? 'success' : 'danger') : 'info'

  return (
    <Page>
      <PageHeader
        title="Inteligência Artificial"
        description="Configure a integração do assistente financeiro com qualquer API compatível com OpenAI."
        breadcrumb={[{ label: 'Dashboard', to: '/dashboard' }, { label: 'Inteligência Artificial' }]}
      />

      <PageContent>
        <ConnectionStatusCard settings={settings} />

        <Card>
          <CardContent>
            <Form form={form} onSubmit={handleSubmit} className="space-y-8">
              <Section title="Ativação">
                <SwitchField
                  name="enabled"
                  label="Habilitar assistente de IA"
                  hint="Disponibiliza o assistente para os usuários com permissão de uso."
                />
              </Section>

              <Section title="Endpoint da API" description="URL base compatível com a API da OpenAI.">
                <TextField
                  name="endpoint"
                  label="Endpoint"
                  placeholder="https://api.openai.com/v1"
                  hint="Ex.: https://openrouter.ai/api/v1, http://ollama:11434/v1"
                />
              </Section>

              <Section title="Autenticação">
                <TextField
                  name="api_key"
                  label="Chave da API"
                  type="password"
                  placeholder={settings?.has_api_key ? '••••••••••••••••' : 'sk-...'}
                  hint={
                    settings?.has_api_key
                      ? 'Uma chave já está salva (criptografada). Preencha para substituí-la.'
                      : 'A chave será armazenada criptografada.'
                  }
                />
              </Section>

              <Section title="Modelo">
                <TextField
                  name="model"
                  label="Modelo"
                  placeholder="gpt-5-mini"
                  hint="Ex.: gpt-5, gemini-2.5-pro, qwen3, llama-3.3, deepseek-chat"
                />
              </Section>

              <Section title="Parâmetros de geração">
                <div className="grid gap-4 sm:grid-cols-2">
                  <TextField name="temperature" label="Temperatura" type="number" step="0.1" hint="Padrão: 0.2 (0 a 2)." />
                  <TextField name="max_tokens" label="Limite máximo de tokens" type="number" hint="Opcional." />
                </div>
              </Section>

              <Section title="Prompt do tenant" description="Instruções adicionais concatenadas ao prompt padrão.">
                <TextareaField
                  name="system_prompt"
                  label="Instruções adicionais"
                  placeholder="Sempre responda utilizando terminologia contábil."
                  rows={4}
                />
              </Section>

              <Section title="Status da integração">
                <div className="space-y-3">
                  <Button type="button" variant="secondary" onClick={handleTest} loading={testConnection.isPending}>
                    <Plug className="size-4" />
                    Testar conexão
                  </Button>
                  {testResult && (
                    <Alert variant={variant} title={testResult.message}>
                      {testResult.status === 'valid' && 'Endpoint, autenticação e modelo validados com sucesso.'}
                      {testResult.status === 'auth' && 'Revise a chave da API informada.'}
                      {testResult.status === 'model' && 'Verifique se o modelo informado existe no provedor.'}
                      {testResult.status === 'endpoint' && 'Confira a URL e se o serviço está acessível.'}
                    </Alert>
                  )}
                </div>
              </Section>

              <div className="flex justify-end">
                <Button type="submit" loading={updateSettings.isPending}>
                  Salvar configurações
                </Button>
              </div>
            </Form>
          </CardContent>
        </Card>
      </PageContent>
    </Page>
  )
}
