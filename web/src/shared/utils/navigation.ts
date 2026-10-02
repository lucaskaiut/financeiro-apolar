export interface ReturnToState {
  from?: string
}

export function returnToState(from: string): ReturnToState {
  return { from }
}

export function getReturnTo(state: unknown, fallback: string): string {
  return (state as ReturnToState | null)?.from ?? fallback
}
