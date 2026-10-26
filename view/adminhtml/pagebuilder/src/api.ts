import { computed, reactive, readonly } from 'vue'
import { usePageBuilderStore } from './stores/pageBuilder'
import { usePreviewStore } from './stores/preview'

let api: ReturnType<typeof create> | undefined

function create() {
  const store = usePageBuilderStore()
  const preview = usePreviewStore()
  return readonly(reactive({
    layout: computed(() => store.serializedLayout),
    selectedNodeId: computed(() => store.selectedNode?.id ?? null),
    components: computed(() => store.config.components),
    editableZones: computed(() => store.outlineZones),
    canEditTheme: computed(() => store.config.canEditTheme),
    themeFields: computed(() => store.themeFields),
    theme: computed(() => store.theme),
    activeTab: computed(() => store.activeTab),
    device: computed(() => preview.device),
    frameAway: computed(() => preview.frameAway),
    applyLayout: store.applyLayout,
    normalizeTheme: store.normalizeTheme,
    applyTheme: store.applyTheme,
    openTab: store.openTab,
    selectNode: (id: string) => store.selectFromPreview(id),
    highlight: (id: string | null) => preview.highlight(id),
  }))
}

/**
 * What an extension gets of the editor: values to read, and the actions that keep the editor's rules
 */
export function usePageBuilder() {
  return (api ??= create())
}
