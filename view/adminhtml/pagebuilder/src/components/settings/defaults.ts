import type { FieldDef } from './types'

/**
 * The settings a node starts with: every `default` its component declares in di.xml (Registry passes them through)
 */
export function settingsDefaults(defs: Record<string, FieldDef> | undefined) {
  const out: Record<string, unknown> = {}
  for (const [key, def] of Object.entries(defs ?? {})) {
    if (!('default' in def)) continue
    const value = def.default
    out[key] = String(def.type ?? '').endsWith('_i18n') && typeof value === 'string'
      ? { default: value }
      : JSON.parse(JSON.stringify(value))
  }
  return out
}
