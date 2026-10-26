// Built into pagebuilder.min.css, which pagebuilder.phtml links
import './styles.css'
import { createApp } from 'vue'
import { createPinia, setActivePinia } from 'pinia'
import PageBuilderPanel from '@/components/PageBuilderPanel.vue'
import { usePageBuilderStore } from '@/stores/pageBuilder'

/**
 * Extensions are separate bundles, named by the server (Api\ExtensionInterface) and loaded here once the page has them
 */
async function loadExtensions(entries: { script: string; config?: Record<string, unknown> }[]) {
  const loaded: Record<string, unknown>[] = []
  for (const { script, config } of entries) {
    try {
      const mod = await import(/* @vite-ignore */ script)
      loaded.push(await mod.default(config ?? {}))
    } catch (e) {
      console.error('[Lynx] extension failed to load:', script, e)
    }
  }
  return loaded
}

async function mount() {
  const el = document.getElementById('lynx-pagebuilder')
  if (!el) return
  const cfg = (window as unknown as { __lynxPageBuilder?: Record<string, any> }).__lynxPageBuilder ?? {}
  // Filled before the extensions load, so they can use the stores at once
  const pinia = createPinia()
  setActivePinia(pinia)
  usePageBuilderStore().init(cfg)
  const extensions = await loadExtensions(cfg.extensions ?? [])
  createApp(PageBuilderPanel, { extensions }).use(pinia).mount(el)
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', mount)
} else {
  mount()
}
