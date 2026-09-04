import { useEffect, useState } from 'react'

/**
 * Dispara `true` logo após a montagem, permitindo animações de entrada
 * (transições CSS a partir do estado inicial) nos gráficos.
 */
export function useMounted(): boolean {
  const [mounted, setMounted] = useState(false)

  useEffect(() => {
    const frame = requestAnimationFrame(() => setMounted(true))

    return () => cancelAnimationFrame(frame)
  }, [])

  return mounted
}
