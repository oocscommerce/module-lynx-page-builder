import { defineStore } from 'pinia'
import { computed, nextTick, reactive, ref, shallowRef, watch } from 'vue'
import { settingsDefaults } from '@/components/settings/defaults'
import type { ComponentDef, LayoutNode } from '@/components/types'
import { usePreviewStore } from './preview'

type AnyRecord = Record<string, any>
type StoredLayout = Record<string, AnyRecord[]>

/** Block\Adminhtml\PageBuilder's config, as pagebuilder.phtml prints it */
export type PageBuilderConfig = {
  layout: StoredLayout
  components: ComponentDef[]
  // Every registered component name; a node naming none of them lost its module
  registeredComponents: string[]
  scopeOptions: any[]
  currentScope: string
  pageOptions: any[]
  currentPage: string
  // The other designs, each with the URL answering its content zone for this page
  designOptions: AnyRecord[]
  saveUrl: string
  // False for a design that may not be written to
  canSave: boolean
  themeSettings: { fields?: AnyRecord; values?: AnyRecord }
  pageTitle: string
  // The zones this editor may change; empty means all
  editableZones: string[]
  // Whether the Theme tab is offered: the theme is the design's, not a layout's
  canEditTheme: boolean
  exitUrl: string
  previewUrl: string
  previewToken: string
  previewRoute: string
  previewStoreCode: string
  // For "Edit this page" on a page the preview frame reached by a link
  framePages: AnyRecord[]
  frameEditorUrl: string
  currentTarget: AnyRecord
  // Empty unless the store view the preview renders as does not run a Lynx theme
  themeNotice: string
  // Empty when the admin lacks the media gallery permission
  mediaContentsUrl: string
  mediaUploadUrl: string
  mediaFolderUrl: string
  mediaBaseUrl: string
  exportFilename: string
  // Share preview: { allowed, listUrl, createUrl, revokeUrl, hours }
  share: AnyRecord
}

const DEFAULTS: PageBuilderConfig = {
  layout: {}, components: [], registeredComponents: [], scopeOptions: [], currentScope: '', pageOptions: [],
  currentPage: '', designOptions: [], saveUrl: '', canSave: true, themeSettings: { fields: {}, values: {} },
  pageTitle: 'Lynx Builder', editableZones: [], canEditTheme: true, exitUrl: '', previewUrl: '', previewToken: '',
  previewRoute: '', previewStoreCode: '', framePages: [], frameEditorUrl: '', currentTarget: {}, themeNotice: '',
  mediaContentsUrl: '', mediaUploadUrl: '', mediaFolderUrl: '', mediaBaseUrl: '', exportFilename: '', share: {},
}

// Backstage: components that render nothing
export const ZONES = ['header', 'content', 'footer', 'backstage']
const HISTORY_LIMIT = 50

const isI18n = (def) => typeof def?.type === 'string' && def.type.endsWith('_i18n')

/**
 * Gives every localized setting its object form, { default, <locale>… }
 */
function localizeSettings(settings, defs: Record<string, AnyRecord>) {
  const out = { ...settings }
  for (const [key, def] of Object.entries(defs)) {
    const value = out[key]
    if (isI18n(def)) {
      if (typeof value === 'string') out[key] = { default: value }
    } else if (def.type === 'array' && Array.isArray(value)) {
      out[key] = value.map(item => (item && typeof item === 'object') ? localizeSettings(item, def.fields ?? {}) : item)
    }
  }
  return out
}

function findNode(id, nodes: LayoutNode[]): LayoutNode | null {
  for (const node of nodes) {
    if (node.id === id) return node
    for (const slotNodes of Object.values(node.slots ?? {})) {
      const found = findNode(id, slotNodes)
      if (found) return found
    }
  }
  return null
}

function removeFromNodes(id, nodes: LayoutNode[]) {
  for (let i = 0; i < nodes.length; i++) {
    if (nodes[i]!.id === id) { nodes.splice(i, 1); return true }
    for (const slotNodes of Object.values(nodes[i]!.slots ?? {})) {
      if (removeFromNodes(id, slotNodes)) return true
    }
  }
  return false
}

function stripIds(items) {
  return (items ?? []).map(({ id, slots, ...rest }) => ({
    ...rest,
    slots: Object.fromEntries(
      Object.entries(slots ?? {}).map(([k, v]) => [k, stripIds(v)])
    ),
  }))
}

function serializeNodes(nodes: LayoutNode[], withIds = true): AnyRecord[] {
  return nodes.map(node => {
    const item: AnyRecord = {
      ...(withIds ? { id: node.id } : {}),
      component: node.key,
      name:      node.name,
      label:     node.label,
      props:     { ...(node.settings ?? {}) },
      visible:   node.visible,
    }
    if (node.custom_name) item.custom_name = node.custom_name
    const slots: AnyRecord = {}
    for (const [slotName, slotNodes] of Object.entries(node.slots ?? {})) {
      if (slotNodes?.length) slots[slotName] = serializeNodes(slotNodes, withIds)
    }
    if (Object.keys(slots).length) item.slots = slots
    return item
  })
}

/**
 * The page being built: the server's config, the layout with its selection and history, the theme, and saving
 */
export const usePageBuilderStore = defineStore('pagebuilder', () => {
  const preview = usePreviewStore()
  const config = shallowRef<PageBuilderConfig>({ ...DEFAULTS })

  // By name, since several registrations may share one Vue file; by key for a node with no name
  const componentsByName = computed(() => Object.fromEntries(config.value.components.map(c => [c.name, c])))
  const componentsByKey = computed(() => Object.fromEntries(config.value.components.map(c => [c.key, c])))
  const registeredNames = computed(() => new Set(config.value.registeredComponents))
  function componentOf(node: Partial<LayoutNode> | AnyRecord | null | undefined): ComponentDef | undefined {
    return componentsByName.value[node?.name] ?? componentsByKey.value[node?.key ?? node?.component]
  }
  const isRegistered = (node: Partial<LayoutNode>) => registeredNames.value.has(node?.name ?? '')

  // allowed_groups of a node's slot, as a list; null when the slot takes anything
  function slotAllowedGroups(node: Partial<LayoutNode>, slotName: string) {
    const allowed = componentOf(node)?.slots?.[slotName]?.allowed_groups
    return allowed ? Object.values(allowed) as string[] : null
  }

  // The zones the outline lists; what the editor was not given is held and posted with the draft but never written
  const outlineZones = computed(() => {
    const editable = config.value.editableZones
    return editable.length ? ZONES.filter(zone => editable.includes(zone)) : ZONES
  })
  // Named for the preview, which frames only what can be selected: nothing to restrict when every zone is on offer
  const framedZones = computed(() => (outlineZones.value.length === ZONES.length ? [] : outlineZones.value))

  // Node ids live in the editor only: a stored layout has none, and every node is handed one here when it comes in
  let nextNodeId = 0

  function deserializeNodes(items): LayoutNode[] {
    return (items ?? []).map(item => ({
      id:          item.id ?? String(++nextNodeId),
      key:         item.component,
      name:        item.name ?? '',
      label:       item.label ?? item.component.split('/').pop(),
      custom_name: item.custom_name ?? undefined,
      visible:     item.visible ?? true,
      settings:    localizeSettings(item.props ?? {}, componentOf({ name: item.name, key: item.component })?.settings ?? {}),
      slots: Object.fromEntries(
        Object.entries(item.slots ?? {}).map(([k, v]) => [k, deserializeNodes(v)])
      ),
    }))
  }

  const layout = ref<Record<string, LayoutNode[]>>(Object.fromEntries(ZONES.map(z => [z, []])))
  const selectedNode = ref<LayoutNode | null>(null)
  // Opened by a double click; a single click only selects
  const settingsOpen = ref(false)
  // Where the picker adds: { id: zone or node id, slot, index? }
  const pickerTarget = ref<{ id: string; slot: string | null; index?: number } | null>(null)

  const findInLayout = (id: string) => ZONES.map(zone => findNode(id, layout.value[zone] ?? [])).find(Boolean) ?? null

  function serializeLayout(withIds = true) {
    return Object.fromEntries(ZONES.map(z => [z, serializeNodes(layout.value[z] ?? [], withIds)]))
  }
  const serializedLayout = computed(() => serializeLayout())

  // The preview frame left the page: nothing edits a layout that is not on screen
  watch(() => preview.frameAway, (away) => {
    if (!away) return
    pickerTarget.value = null
    settingsOpen.value = false
  })

  function removeNode(id: string) {
    if (selectedNode.value?.id === id) selectedNode.value = null
    for (const zone of ZONES) removeFromNodes(id, layout.value[zone] ?? [])
  }

  // Clicking the node already selected lets go of it: there is no other empty space to click
  function selectNode(node: LayoutNode) {
    selectedNode.value = selectedNode.value?.id === node.id ? null : node
  }

  function openSettings(node: LayoutNode) {
    if (selectedNode.value?.id === node.id) preview.highlight(node.id)
    selectedNode.value = node
    settingsOpen.value = true
  }

  function openPicker(targetId: string, slotName: string | null = null) {
    selectedNode.value = null
    pickerTarget.value = { id: targetId, slot: slotName }
  }

  function closePicker() {
    pickerTarget.value = null
  }

  function addBlock(component: ComponentDef) {
    if (!pickerTarget.value) return
    const node: LayoutNode = {
      id: String(++nextNodeId),
      key: component.key ?? '',
      name: component.name,
      label: component.label ?? '',
      visible: true,
      // Every default the component declares, so the node is complete from the start.
      settings: settingsDefaults(component.settings),
      slots: {},
    }
    const { id: targetId, slot: slotName, index } = pickerTarget.value
    // At the position a + in the preview asked for, else at the end.
    const put = (list: LayoutNode[]) => (Number.isInteger(index) ? list.splice(index!, 0, node) : list.push(node))
    if (ZONES.includes(targetId)) {
      put(layout.value[targetId]!)
      return
    }
    const parent = findInLayout(targetId)
    if (!parent || !slotName) return
    if (!parent.slots) parent.slots = {}
    if (!parent.slots[slotName]) parent.slots[slotName] = []
    put(parent.slots[slotName])
  }

  const targetZone = computed(() => {
    if (!pickerTarget.value) return null
    const id = pickerTarget.value.id
    if (ZONES.includes(id)) return id
    return ZONES.find(zone => findNode(id, layout.value[zone] ?? [])) ?? null
  })

  const targetParent = computed(() => (pickerTarget.value?.slot ? findInLayout(pickerTarget.value.id) : null))

  // What the picker's title says after "Add component ·": the slot being added into when the + pressed was a slot's own
  const ZONE_LABELS: Record<string, string> = { header: 'Header', content: 'Content', footer: 'Footer', backstage: 'Backstage' }
  const targetLabel = computed(() => {
    const target = pickerTarget.value
    if (!target) return ''
    if (!target.slot) return ZONE_LABELS[targetZone.value ?? ''] ?? ''
    const parent = targetParent.value
    return parent ? componentOf(parent)?.slots?.[target.slot]?.label ?? target.slot : target.slot
  })

  // The groups the target slot accepts (its allowed_groups); null when the target is a zone or an open slot
  const allowedGroups = computed(() =>
    (targetParent.value ? slotAllowedGroups(targetParent.value, pickerTarget.value!.slot!) : null))

  // Registered names the target slot already holds as often as its max_per_component allows; the picker locks them.
  const fullComponents = computed(() => {
    const parent = targetParent.value
    if (!parent) return []
    const slot = pickerTarget.value!.slot!
    const max = Number(componentOf(parent)?.slots?.[slot]?.max_per_component) || 0
    if (!max) return []
    const counts: Record<string, number> = {}
    for (const child of parent.slots?.[slot] ?? []) counts[child.name ?? ''] = (counts[child.name ?? ''] ?? 0) + 1
    return Object.keys(counts).filter(name => counts[name]! >= max)
  })

  /** A node picked in the preview (an outline label): selected here, which the outline follows. */
  function selectFromPreview(id: string) {
    const node = findInLayout(id)
    if (!node) return
    if (selectedNode.value?.id === node.id) preview.highlight(node.id)
    selectedNode.value = node
  }

  /**
   * Where a node sits, as the picker names a place: its container (a zone, or a node and slot) and its index there
   */
  function locate(id: string) {
    const walk = (nodes: LayoutNode[], container: { id: string; slot: string | null }) => {
      for (let i = 0; i < nodes.length; i++) {
        if (nodes[i]!.id === id) return { ...container, index: i }
        for (const [slot, children] of Object.entries(nodes[i]!.slots ?? {})) {
          const found = walk(children, { id: nodes[i]!.id, slot })
          if (found) return found
        }
      }
      return null
    }
    for (const zone of ZONES) {
      const found = walk(layout.value[zone] ?? [], { id: zone, slot: null })
      if (found) return found
    }
    return null
  }

  // A + beside the selected node in the preview: the picker for the node's own container
  function insertFromPreview({ id, position }: { id: string; position: string }) {
    if (preview.frameAway) return
    const at = locate(id)
    if (!at) return
    selectedNode.value = null
    pickerTarget.value = { id: at.id, slot: at.slot, index: position === 'after' ? at.index + 1 : at.index }
  }

  // A click on an empty slot's placeholder in the preview
  function addFromPreview({ id, slot }: { id: string; slot: string }) {
    if (preview.frameAway || !findInLayout(id)) return
    selectedNode.value = null
    pickerTarget.value = { id, slot }
  }

  const past = ref<string[]>([])
  const future = ref<string[]>([])
  let current = JSON.stringify(serializedLayout.value)
  // Set while undo/redo writes the layout back, so the watcher updates `current` without recording the write as a new
  // step
  let restoring = false

  watch(serializedLayout, (value) => {
    const next = JSON.stringify(value)
    if (next === current) return
    if (!restoring) {
      past.value.push(current)
      if (past.value.length > HISTORY_LIMIT) past.value.shift()
      future.value = []
    }
    current = next
  })

  const canUndo = computed(() => !preview.frameAway && past.value.length > 0)
  const canRedo = computed(() => !preview.frameAway && future.value.length > 0)

  function restoreSnapshot(json: string) {
    restoring = true
    const snapshot = JSON.parse(json)
    for (const zone of ZONES) {
      layout.value[zone] = deserializeNodes(snapshot[zone] ?? [])
    }
    // The nodes are new objects now; the selection points at the old ones
    if (selectedNode.value) {
      const found = findInLayout(selectedNode.value.id)
      selectedNode.value = found
      if (!found) settingsOpen.value = false
    }
    pickerTarget.value = null
    nextTick(() => { restoring = false })
  }

  function undo() {
    if (!canUndo.value) return
    future.value.push(current)
    restoreSnapshot(past.value.pop()!)
  }

  function redo() {
    if (!canRedo.value) return
    past.value.push(current)
    restoreSnapshot(future.value.pop()!)
  }

  function replaceZones(zones: string[], source: StoredLayout) {
    for (const zone of zones) layout.value[zone] = deserializeNodes(stripIds(source[zone] ?? []))
    selectedNode.value = null
    pickerTarget.value = null
  }

  /** Replaces the layout with one in the stored shape; only the zones this editor may change are written. */
  function applyLayout(stored: StoredLayout) {
    replaceZones(outlineZones.value, stored ?? {})
  }

  function exportLayout() {
    const json = JSON.stringify(serializeLayout(false), null, 2)
    const a = document.createElement('a')
    a.href = URL.createObjectURL(new Blob([json], { type: 'application/json' }))
    a.download = config.value.exportFilename
    a.click()
    URL.revokeObjectURL(a.href)
  }

  function importLayout(text: string) {
    try {
      const data = JSON.parse(text)
      if (!data || typeof data !== 'object' || Array.isArray(data)) {
        notify('error', 'That file is not a layout export.')
        return
      }
      for (const zone of ZONES) layout.value[zone] = deserializeNodes(data[zone] ?? [])
      selectedNode.value = null
      pickerTarget.value = null
      notify('success', 'Layout imported. Save to keep it.')
    } catch {
      notify('error', 'That file could not be read as JSON.')
    }
  }

  /**
   * Replaces this page's content zone with what another design holds for the same page
   */
  async function copyFromDesign(source: AnyRecord | null) {
    if (!source?.url) return
    try {
      const res = await fetch(source.url, { headers: { Accept: 'application/json' } })
      const data: any = res.ok ? await res.json().catch(() => null) : null
      if (!data || typeof data !== 'object') {
        notify('error', 'The layout could not be loaded.')
        return
      }
      const nodes = Array.isArray(data.content) ? data.content : []
      if (!nodes.length) {
        notify('error', `${source.label} has no layout for this page.`)
        return
      }
      replaceZones(['content'], { content: nodes })
      notify('success', `Page content copied from "${source.label}". Save to keep it.`)
    } catch (e: any) {
      notify('error', e?.message || 'The server could not be reached.')
    }
  }

  // What the design has saved: the Theme tab's Reset goes back to it, and it moves on with every successful Save
  const themeSaved = ref<AnyRecord>({})
  const theme = reactive<AnyRecord>({})
  const themeFields = computed<Record<string, AnyRecord>>(() => config.value.themeSettings.fields ?? {})

  // Swatches for FieldColor
  const themeColors = computed(() =>
    Object.entries(themeFields.value)
      .filter(([, def]) => def.type === 'color')
      .map(([key]) => theme[key])
      .filter(Boolean) as string[])

  /**
   * Theme values held to the fields the way the server's ThemeSettings::normalize() holds a save
   */
  function normalizeTheme(values: AnyRecord) {
    const next: AnyRecord = {}
    for (const [key, raw] of Object.entries(values ?? {})) {
      const field = themeFields.value[key]
      if (!field) continue
      let value: any = raw
      if (field.type === 'number') {
        value = Number.parseFloat(String(raw))
        if (Number.isNaN(value)) continue
        if (field.min !== undefined) value = Math.max(Number(field.min), value)
        if (field.max !== undefined) value = Math.min(Number(field.max), value)
        value = String(value)
      } else if (field.type === 'select') {
        if (!Object.values<AnyRecord>(field.options ?? {}).some(o => String(o.value) === String(raw))) continue
        value = String(raw)
      } else {
        value = String(raw ?? '').trim()
      }
      next[key] = value
    }
    return next
  }

  /** What normalizeTheme() lets through lands on `theme`, which the Settings card, the preview and Save all read. */
  function applyTheme(values: AnyRecord) {
    // The theme is the design's
    if (!config.value.canEditTheme) return { ...theme }
    const next = normalizeTheme(values)
    Object.assign(theme, next)
    return next
  }

  // One notice at a time, shown as a strip under the top bar
  const notice = ref<{ type: string; text: string } | null>(null)
  let noticeTimer: ReturnType<typeof setTimeout> | undefined

  function notify(type: string, text: string) {
    notice.value = { type, text }
    clearTimeout(noticeTimer)
    // An error has to be read; a confirmation only has to be noticed.
    noticeTimer = setTimeout(() => { notice.value = null }, type === 'error' ? 8000 : 3000)
  }

  const saving = ref(false)
  const saveStatus = ref('idle')

  async function save() {
    const { saveUrl, canSave } = config.value
    if (!saveUrl || !canSave || saving.value) return
    saving.value = true
    saveStatus.value = 'idle'
    try {
      const body = new FormData()
      body.append('form_key', (window as any).FORM_KEY ?? '')
      body.append('layout', JSON.stringify(serializeLayout(false)))
      body.append('theme', JSON.stringify(theme))
      const res = await fetch(saveUrl, { method: 'POST', body })
      // The controller answers {success, message} and says failure in the status code as well
      const data: any = await res.json().catch(() => null)
      const ok = res.ok && data?.success !== false
      if (ok) themeSaved.value = { ...theme }
      saveStatus.value = ok ? 'ok' : 'err'
      notify(ok ? 'success' : 'error', data?.message || (ok ? 'Layout saved.' : 'Saving the layout failed.'))
    } catch (e: any) {
      saveStatus.value = 'err'
      notify('error', e?.message || 'The server could not be reached.')
    } finally {
      saving.value = false
      setTimeout(() => { saveStatus.value = 'idle' }, 2500)
    }
  }

  // 'outline', 'theme', or an extension's tab
  const activeTab = ref('outline')
  const cardOpen = ref(true)

  function openTab(tab: string) {
    activeTab.value = tab
    cardOpen.value = true
  }

  /** Takes the server's config once, before anything reads the store. */
  function init(cfg: Partial<PageBuilderConfig>) {
    config.value = { ...DEFAULTS, ...cfg }
    for (const zone of ZONES) layout.value[zone] = deserializeNodes(config.value.layout[zone] ?? [])
    // The loaded layout is where history starts, not a step in it
    current = JSON.stringify(serializeLayout())
    const values = config.value.themeSettings.values ?? {}
    themeSaved.value = { ...values }
    Object.assign(theme, values)
  }

  return {
    config, init,
    componentOf, isRegistered, slotAllowedGroups, outlineZones, framedZones,
    layout, selectedNode, settingsOpen, pickerTarget, serializedLayout,
    removeNode, selectNode, openSettings, openPicker, closePicker, addBlock,
    targetZone, targetLabel, allowedGroups, fullComponents,
    selectFromPreview, insertFromPreview, addFromPreview,
    past, future, canUndo, canRedo, undo, redo,
    applyLayout, exportLayout, importLayout, copyFromDesign,
    theme, themeSaved, themeFields, themeColors, normalizeTheme, applyTheme,
    notice, notify, saving, saveStatus, save,
    activeTab, cardOpen, openTab,
  }
})
