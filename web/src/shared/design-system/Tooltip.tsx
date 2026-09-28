import { useRef, useState, type ReactNode } from 'react'
import { createPortal } from 'react-dom'
import { cn } from '@/shared/utils/cn'

export function Tooltip({
  label,
  children,
  side = 'top',
  className,
}: {
  label: string
  children: ReactNode
  side?: 'top' | 'bottom' | 'right'
  className?: string
}) {
  const [open, setOpen] = useState(false)
  const [position, setPosition] = useState({ top: 0, left: 0 })
  const triggerRef = useRef<HTMLSpanElement>(null)

  const show = () => {
    const rect = triggerRef.current?.getBoundingClientRect()
    if (!rect) return

    const gap = 8

    if (side === 'right') {
      setPosition({ top: rect.top + rect.height / 2, left: rect.right + gap })
    } else {
      setPosition({
        top: side === 'top' ? rect.top - gap : rect.bottom + gap,
        left: rect.left + rect.width / 2,
      })
    }

    setOpen(true)
  }

  const hide = () => setOpen(false)

  return (
    <>
      <span
        ref={triggerRef}
        className={cn('inline-flex', className)}
        onMouseEnter={show}
        onMouseLeave={hide}
        onFocus={show}
        onBlur={hide}
      >
        {children}
      </span>
      {open &&
        createPortal(
          <div
            role="tooltip"
            style={{
              top: position.top,
              left: position.left,
              transform:
                side === 'top'
                  ? 'translate(-50%, -100%)'
                  : side === 'bottom'
                    ? 'translate(-50%, 0)'
                    : 'translate(0, -50%)',
            }}
            className="pointer-events-none fixed z-[100] max-w-64 rounded-md bg-foreground px-2.5 py-1.5 text-xs font-medium whitespace-nowrap text-background shadow-pop"
          >
            {label}
          </div>,
          document.body,
        )}
    </>
  )
}
