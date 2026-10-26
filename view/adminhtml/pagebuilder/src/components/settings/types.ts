/** One setting as the component declares it in di.xml, passed through by the server. */
export type FieldDef = {
  type?: string
  label?: string
  placeholder?: string
  rows?: number
  mono?: boolean
  options?: Record<string, { value: string; label: string }>
  min?: number
  max?: number
  step?: number
  default?: unknown
  // array: the fields of one item, and the one its row is named by
  fields?: Record<string, FieldDef>
  labelField?: string
  [key: string]: unknown
}
