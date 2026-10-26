import { flushPromises, mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import { reactive } from 'vue'
import SettingsPanel from '@/components/SettingsPanel.vue'
import { TooltipProvider } from '@/ui/tooltip'
import { usePageBuilderStore } from '@/stores/pageBuilder'
import type { LayoutNode } from '@/components/types'

const SETTINGS = {
  layout: { type: 'select', label: 'Layout', default: 'grid', options: { grid: { value: 'grid', label: 'Grid' }, list: { value: 'list', label: 'List' } } },
  columns: { type: 'number', label: 'Columns', default: 3, depends: { layout: 'grid' } },
  link: { type: 'url', label: 'Link' },
  title: { type: 'text', label: 'Title' },
}

function mountPanel(node: Partial<LayoutNode>, settings: Record<string, unknown> = SETTINGS) {
  usePageBuilderStore().init({ components: [{ key: 'Vendor_Module/Components/Thing', settings, description: 'A thing' }] })
  const reactiveNode = reactive({ id: '1', key: 'Vendor_Module/Components/Thing', label: 'Thing', ...node }) as LayoutNode
  const wrapper = mount(
    { components: { SettingsPanel, TooltipProvider }, setup: () => ({ node: reactiveNode }), template: '<TooltipProvider><SettingsPanel :node="node" /></TooltipProvider>' },
    { attachTo: document.body },
  )
  return { wrapper, node: reactiveNode, panel: () => wrapper.findComponent(SettingsPanel) }
}

const button = (wrapper: ReturnType<typeof mount>, text: string) => wrapper.findAll('button').find(b => b.text() === text)!
const labels = (wrapper: ReturnType<typeof mount>) => wrapper.findAll('label').map(l => l.text())

describe('SettingsPanel', () => {
  it('shows a setting only while its depends hold, and starts every setting from its default', () => {
    const { wrapper } = mountPanel({ settings: {} })
    expect(labels(wrapper)).toEqual(['Layout', 'Columns', 'Link', 'Title'])
    const hidden = mountPanel({ settings: { layout: 'list' } })
    expect(labels(hidden.wrapper)).toEqual(['Layout', 'Link', 'Title'])
  })

  it('saves only when something changed, and drops a setting its depends hide', async () => {
    const { wrapper, node } = mountPanel({ settings: { layout: 'list', columns: 4 } })
    const save = button(wrapper, 'Save')
    expect(save.attributes('disabled')).toBeDefined()
    await wrapper.findAll('input[type=text]').at(-1)!.setValue('Hello')
    expect(save.attributes('disabled')).toBeUndefined()
    await save.trigger('click')
    expect(node.settings).toEqual({ layout: 'list', title: 'Hello' })
  })

  it('will not save a broken absolute URL', async () => {
    const { wrapper, node } = mountPanel({ settings: {} })
    await wrapper.findAll('input[type=text]')[0]!.setValue('https://')
    await button(wrapper, 'Save').trigger('click')
    expect(wrapper.text()).toContain('"Link" contains an invalid value.')
    expect(node.settings).toEqual({})
  })

  it('asks before closing over unsaved changes', async () => {
    const { wrapper, panel } = mountPanel({ settings: {} })
    await wrapper.find('button[title=Close]').trigger('click')
    expect(panel().emitted('close')).toHaveLength(1)

    await wrapper.findAll('input[type=text]').at(-1)!.setValue('Changed')
    await wrapper.find('button[title=Close]').trigger('click')
    expect(wrapper.text()).toContain('Discard changes?')
    expect(panel().emitted('close')).toHaveLength(1)
    await button(wrapper, 'Yes').trigger('click')
    expect(panel().emitted('close')).toHaveLength(2)
  })

  it('renames the node in place, and an empty name goes back to the label', async () => {
    const { wrapper, node } = mountPanel({ settings: {} })
    await wrapper.find('[title=Thing]').trigger('click')
    await flushPromises()
    const input = wrapper.find('input[placeholder=Thing]')
    await input.setValue('Hero')
    await input.trigger('keydown', { key: 'Enter' })
    expect(node.custom_name).toBe('Hero')

    await wrapper.find('[title=Hero]').trigger('click')
    await wrapper.find('input[placeholder=Thing]').setValue('  ')
    await wrapper.find('input[placeholder=Thing]').trigger('keydown', { key: 'Enter' })
    expect(node.custom_name).toBeUndefined()
  })

  it('groups the settings that name a tab, General first and open', () => {
    const { wrapper } = mountPanel({ settings: {} }, {
      color: { type: 'text', label: 'Colour', tab: 'Style' },
      title: { type: 'text', label: 'Title' },
    })
    const triggers = wrapper.findAll('[data-slot=accordion-trigger]').map(t => t.text())
    expect(triggers).toEqual(['General', 'Style'])
    expect(labels(wrapper)).toEqual(['Title'])
  })
})
