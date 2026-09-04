import { useEffect, useState } from 'react'
import { FileImage, Trash2 } from 'lucide-react'
import { buttonClasses } from '@/shared/design-system'
import { formatBytes } from '@/shared/utils/format'
import { cn } from '@/shared/utils/cn'
import { FILE_KIND_ICON, fileKind, kindLabel } from './document-kind'
import { DocumentDropzone } from './DocumentDropzone'

interface PendingDocumentsProps {
  files: File[]
  onChange: (files: File[]) => void
  disabled?: boolean
}

export function PendingDocuments({ files, onChange, disabled = false }: PendingDocumentsProps) {
  const [previews, setPreviews] = useState<string[]>([])

  useEffect(() => {
    const next = files.map((file) => (file.type.startsWith('image/') ? URL.createObjectURL(file) : ''))

    setPreviews(next)

    return () => {
      next.forEach((url) => {
        if (url) URL.revokeObjectURL(url)
      })
    }
  }, [files])

  const removeAt = (index: number) => {
    onChange(files.filter((_, i) => i !== index))
  }

  return (
    <div className="space-y-4">
      {!disabled && <DocumentDropzone onFiles={(list) => onChange([...files, ...list])} />}

      {files.length > 0 && (
        <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
          {files.map((file, index) => {
            const kind = fileKind(file.type)
            const KindIcon = kind === 'image' ? FileImage : FILE_KIND_ICON[kind]
            const preview = previews[index]

            return (
              <li
                key={`${file.name}-${file.size}-${file.lastModified}-${index}`}
                className="overflow-hidden rounded-xl border border-surface-3 bg-surface-2/40"
              >
                <div className="flex h-28 items-center justify-center overflow-hidden bg-surface-2">
                  {kind === 'image' && preview ? (
                    <img src={preview} alt={file.name} className="size-full object-cover" />
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
                  <p className="truncate text-[13px] font-medium text-foreground" title={file.name}>
                    {file.name}
                  </p>
                  <p className="mt-0.5 text-[12px] text-muted">{formatBytes(file.size)}</p>
                </div>
                <div className="flex items-center justify-end border-t border-surface-3 px-1.5 py-1">
                  <button
                    type="button"
                    disabled={disabled}
                    onClick={() => removeAt(index)}
                    className={cn(buttonClasses('ghost', 'sm'), 'text-danger hover:bg-danger-soft hover:text-danger')}
                    aria-label={`Remover ${file.name}`}
                  >
                    <Trash2 className="size-4" />
                  </button>
                </div>
              </li>
            )
          })}
        </ul>
      )}
    </div>
  )
}
