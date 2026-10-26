import type { ClassValue } from 'clsx'
import { clsx } from 'clsx'
import { twMerge } from 'tailwind-merge'

/** Joins class lists, a later Tailwind utility overriding an earlier one of the same kind. */
export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs))
}
