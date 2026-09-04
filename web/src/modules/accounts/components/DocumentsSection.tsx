import { useCallback, useState } from 'react'
import { Download, Eye, File, FileImage, Paperclip, Trash2 } from 'lucide-react'
import { API_BASE_URL } from '@/shared/api/base-url'
import {
  Badge,
  buttonClasses,
  Card,
  CardContent,
  CardHeader,
  ConfirmDialog,
  EmptyState,
  Modal,
  Skeleton,
} from '@/shared/design-system'
import { usePermissions } from '@/shared/hooks/usePermissions'
import { Permission } from '@/shared/constants/permissions'
import { formatBytes, formatDateTime } from '@/shared/utils/format'
import { cn } from '@/shared/utils/cn'
import type { AccountDocument } from '@/shared/types/models'
import { useAccountDocuments, useDeleteDocument, useUploadDocuments } from '../hooks/useAccounts'
import { FILE_KIND_ICON, fileKind, kindLabel } from './document-kind'
import { DocumentDropzone } from './DocumentDropzone'

interface DocumentsSectionProps {
  accountId: string
}

export function DocumentsSection({ accountId }: DocumentsSectionProps) {
  const [preview, setPreview] = useState<AccountDocument | null>(null)
  const [toDelete, setToDelete] = useState<AccountDocument | null>(null)

  const { can } = usePermissions()
  const canEdit = can(Permission.ACCOUNTS_UPDATE)

  const documents = useAccountDocuments(accountId)
  const upload = useUploadDocuments(accountId)
  const remove = useDeleteDocument(accountId)

  const fileUrl = useCallback(
    (doc: AccountDocument, download = false): string => {
      const base = `${API_BASE_URL}/api/accounts/${accountId}/documents/${doc.id}/download`

      return download ? `${base}?download=1` : base
    },
    [accountId],
  )

  const items = documents.data ?? []
  const previewKind = preview ? fileKind(preview.mime_type) : null

  return (
    <Card>
      <CardHeader
        title={
          <span className="flex items-center gap-2">
            <Paperclip className="size-4 text-muted" aria-hidden="true" />
            Documentos anexos
            {items.length > 0 && <Badge variant="neutral">{items.length}</Badge>}
          </span>
        }
        description="Anexe faturas, boletos, recibos e outros comprovantes deste lançamento."
      />

      <CardContent className="space-y-5">
        {canEdit && <DocumentDropzone onFiles={upload.mutate} uploading={upload.isPending} />}

        {documents.isPending && (
          <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
            {Array.from({ length: 4 }).map((_, index) => (
              <Skeleton key={index} className="h-40 w-full" />
            ))}
          </div>
        )}

        {!documents.isPending && items.length === 0 && (
          <EmptyState
            icon={Paperclip}
            title="Nenhum documento anexado"
            description={canEdit ? 'Use a área acima para anexar o primeiro documento.' : 'Nenhum documento foi anexado a este lançamento.'}
          />
        )}

        {!documents.isPending && items.length > 0 && (
          <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
            {items.map((doc) => {
              const kind = fileKind(doc.mime_type)
              const KindIcon = kind === 'image' ? FileImage : FILE_KIND_ICON[kind]

              return (
                <li key={doc.id} className="group overflow-hidden rounded-xl border border-surface-3 bg-surface-2/40 transition-colors hover:border-surface-3 hover:bg-surface-2">
                  <button
                    type="button"
                    onClick={() => setPreview(doc)}
                    className="block w-full text-left"
                    aria-label={`Visualizar ${doc.name}`}
                  >
                    <div className="flex h-28 items-center justify-center overflow-hidden bg-surface-2">
                      {kind === 'image' ? (
                        <img src={fileUrl(doc)} alt={doc.name} className="size-full object-cover transition-transform duration-200 group-hover:scale-105" loading="lazy" />
                      ) : (
                        <div className="flex flex-col items-center gap-1.5 text-subtle">
                          <KindIcon className="size-9" aria-hidden="true" />
                          <span className="text-[11px] font-semibold uppercase tracking-wide text-muted">
                            {kindLabel(kind)}
                          </span>
                        </div>
                      )}
                    </div>
                    <div className="p-3">
                      <p className="truncate text-[13px] font-medium text-foreground" title={doc.name}>
                        {doc.name}
                      </p>
                      <p className="mt-0.5 text-[12px] text-muted">{formatBytes(doc.size)}</p>
                    </div>
                  </button>

                  <div className="flex items-center justify-between border-t border-surface-3 px-1.5 py-1">
                    <a
                      href={fileUrl(doc, true)}
                      target="_blank"
                      rel="noreferrer"
                      className={buttonClasses('ghost', 'sm')}
                      aria-label={`Baixar ${doc.name}`}
                    >
                      <Download className="size-4" />
                    </a>
                    <button
                      type="button"
                      onClick={() => setPreview(doc)}
                      className={cn(buttonClasses('ghost', 'sm'), 'text-muted')}
                      aria-label={`Visualizar ${doc.name}`}
                    >
                      <Eye className="size-4" />
                    </button>
                    {canEdit && (
                      <button
                        type="button"
                        onClick={() => setToDelete(doc)}
                        className={cn(buttonClasses('ghost', 'sm'), 'text-danger hover:bg-danger-soft hover:text-danger')}
                        aria-label={`Excluir ${doc.name}`}
                      >
                        <Trash2 className="size-4" />
                      </button>
                    )}
                  </div>
                </li>
              )
            })}
          </ul>
        )}
      </CardContent>

      <Modal
        open={preview !== null}
        onClose={() => setPreview(null)}
        title={preview?.name ?? ''}
        description={
          preview
            ? `${formatBytes(preview.size)} · ${preview.mime_type ?? 'arquivo'} · ${formatDateTime(preview.created_at)}`
            : undefined
        }
        size="lg"
      >
        {preview && previewKind === 'image' && (
          <img src={fileUrl(preview)} alt={preview.name} className="max-h-[70vh] w-full rounded-lg object-contain" />
        )}

        {preview && previewKind === 'pdf' && (
          <iframe src={fileUrl(preview)} title={preview.name} className="h-[70vh] w-full rounded-lg border border-surface-3" />
        )}

        {preview && previewKind !== 'image' && previewKind !== 'pdf' && (
          <EmptyState
            icon={File}
            title="Pré-visualização indisponível"
            description="Este tipo de arquivo não possui pré-visualização. Baixe o documento para visualizá-lo."
            action={
              <a href={fileUrl(preview, true)} target="_blank" rel="noreferrer" className={buttonClasses('primary', 'md')}>
                <Download className="size-4" />
                Baixar arquivo
              </a>
            }
          />
        )}
      </Modal>

      <ConfirmDialog
        open={toDelete !== null}
        onClose={() => setToDelete(null)}
        onConfirm={() => {
          if (toDelete) remove.mutate(toDelete.id, { onSettled: () => setToDelete(null) })
        }}
        loading={remove.isPending}
        title="Excluir documento"
        description={<>Tem certeza que deseja excluir <strong>{toDelete?.name}</strong>?</>}
        confirmLabel="Excluir"
      />
    </Card>
  )
}
