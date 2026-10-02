import { useEffect, useLayoutEffect, useRef } from 'react'
import { useLocation } from 'react-router'

export const SCROLL_CONTAINER_ID = 'app-scroll-container'

const STORAGE_PREFIX = 'scroll-position:'
const RESTORE_TIMEOUT_MS = 1000

function getScrollContainer(): HTMLElement | null {
  return document.getElementById(SCROLL_CONTAINER_ID)
}

function readScrollPosition(key: string): number {
  const value = sessionStorage.getItem(`${STORAGE_PREFIX}${key}`)

  return value ? Number(value) : 0
}

function writeScrollPosition(key: string, position: number): void {
  sessionStorage.setItem(`${STORAGE_PREFIX}${key}`, String(position))
}

export function useScrollRestoration(): void {
  const location = useLocation()
  const key = `${location.pathname}${location.search}`
  const currentKeyRef = useRef(key)
  const restoringRef = useRef(false)

  useEffect(() => {
    const container = getScrollContainer()

    if (!container) return

    const handleScroll = () => {
      if (currentKeyRef.current !== key || restoringRef.current) return

      writeScrollPosition(key, container.scrollTop)
    }

    container.addEventListener('scroll', handleScroll, { passive: true })

    return () => container.removeEventListener('scroll', handleScroll)
  }, [key])

  useLayoutEffect(() => {
    currentKeyRef.current = key

    const container = getScrollContainer()

    if (!container) return

    const saved = readScrollPosition(key)

    if (saved <= 0) {
      container.scrollTop = 0
      return
    }

    restoringRef.current = true

    let frame = 0
    const startedAt = performance.now()

    const restore = () => {
      container.scrollTop = saved

      if (container.scrollTop < saved && performance.now() - startedAt < RESTORE_TIMEOUT_MS) {
        frame = requestAnimationFrame(restore)
      } else {
        restoringRef.current = false
      }
    }

    restore()

    return () => {
      cancelAnimationFrame(frame)
      restoringRef.current = false
    }
  }, [key])
}
