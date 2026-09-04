import { useRef, useState } from 'react'
import { Plus, UploadCloud } from 'lucide-react'
import { cn } from '@/shared/utils/cn'

const ACCEPT = '.pdf,.jpg,.jpeg,.png,.gif,.webp,.bmp,.svg,.doc,.docx,.xls,.xlsx,.csv,.txt'

interface DocumentDropzoneProps {
  onFiles: (files: File[]) => void
  uploading?: boolean
  className?: string
}

export function DocumentDropzone({ onFiles, uploading = false, className }: DocumentDropzoneProps) {
  const inputRef = useRef<HTMLInputElement>(null)
  const [dragging, setDragging] = useState(false)

  const handleFiles = (files: FileList | null) => {
    if (!files || files.length === 0) return

    onFiles(Array.from(files))
  }

  return (
    <div
      onDragOver={(event) => {
        event.preventDefault()
        setDragging(true)
      }}
      onDragLeave={() => setDragging(false)}
      onDrop={(event) => {
        event.preventDefault()
        setDragging(false)
        handleFiles(event.dataTransfer.files)
      }}
      className={cn(
        'flex cursor-pointer flex-col items-center justify-center gap-3 rounded-xl border-2 border-dashed p-8 text-center transition-colors',
        dragging
          ? 'border-primary bg-primary-soft'
          : 'border-surface-3 bg-surface-2/50 hover:border-primary/60 hover:bg-surface-2',
        className,
      )}
      onClick={() => inputRef.current?.click()}
      role="button"
      tabIndex={0}
      onKeyDown={(event) => {
        if (event.key === 'Enter' || event.key === ' ') {
          event.preventDefault()
          inputRef.current?.click()
        }
      }}
    >
      {uploading ? (
        <>
          <UploadCloud className="size-9 animate-bounce text-primary" aria-hidden="true" />
          <span className="text-sm font-medium text-foreground">Enviando documentos...</span>
        </>
      ) : (
        <>
          <div className="flex size-11 items-center justify-center rounded-full bg-primary-soft text-primary">
            <Plus className="size-5" aria-hidden="true" />
          </div>
          <div className="space-y-1">
            <p className="text-sm font-medium text-foreground">Arraste arquivos aqui ou clique para anexar</p>
            <p className="text-[13px] text-muted">PDF, imagens e planilhas · até 20 MB por arquivo</p>
          </div>
        </>
      )}
      <input
        ref={inputRef}
        type="file"
        multiple
        accept={ACCEPT}
        className="sr-only"
        onChange={(event) => {
          handleFiles(event.target.files)
          event.target.value = ''
        }}
      />
    </div>
  )
}
