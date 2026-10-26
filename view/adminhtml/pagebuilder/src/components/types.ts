import type { FieldDef } from './settings/types'

/** A node of the layout as the editor holds it: ids are the editor's own, dropped on save. */
export type LayoutNode = {
  id: string
  key: string
  name?: string
  label: string
  custom_name?: string
  visible?: boolean
  settings?: Record<string, any>
  slots?: Record<string, LayoutNode[]>
  [key: string]: any
}

/** A registered component as the server lists it (Registry). */
export type ComponentDef = {
  key?: string
  name?: string
  label?: string
  description?: string
  icon?: string
  group?: string
  settings?: Record<string, FieldDef>
  slots?: Record<string, { label?: string; allowed_groups?: Record<string, string>; max_per_component?: number | string }>
  [key: string]: any
}
