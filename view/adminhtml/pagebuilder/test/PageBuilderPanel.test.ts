import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import PageBuilderPanel from '@/components/PageBuilderPanel.vue'
import { usePageBuilderStore } from '@/stores/pageBuilder'

const COMPONENTS = [
  { name: 'group', key: 'T/Components/Group', label: 'Group', group: 'layout', slots: { default: { label: 'Content' } } },
  { name: 'heading', key: 'T/Components/Heading', label: 'Heading', group: 'text', settings: {
    text: { type: 'text_i18n', label: 'Text', default: 'New heading' },
    level: { type: 'select', label: 'Level', default: 'h2' },
  } },
]

const LAYOUT = {
  header: [],
  content: [{
    component: 'T/Components/Group', name: 'group', label: 'Group', custom_name: 'Hero', props: {},
    slots: { default: [{ component: 'T/Components/Heading', name: 'heading', label: 'Heading', props: { text: 'Hi', level: 'h1' } }] },
  }],
  footer: [],
  backstage: [],
}

function mountPanel() {
  usePageBuilderStore().init({ layout: structuredClone(LAYOUT), components: COMPONENTS, registeredComponents: ['group', 'heading'], saveUrl: '/save', exportFilename: 'layout.json', previewUrl: 'https://shop.test/lynx_pagebuilder/' })
  return mount(PageBuilderPanel, { attachTo: document.body })
}

const rowOf = (wrapper: ReturnType<typeof mount>, name: string) =>
  wrapper.findAll('[data-tree-id] > div').find(row => row.text().includes(name))!
const ctrl = (key: string, shiftKey = false) => window.dispatchEvent(new KeyboardEvent('keydown', { key, ctrlKey: true, shiftKey }))

function stubSave() {
  const fetch = vi.fn(async () => ({ ok: true, json: async () => ({ success: true }) }))
  vi.stubGlobal('fetch', fetch)
  return async (wrapper: ReturnType<typeof mount>) => {
    // The button reads ✓ Saved for a moment after each save
    await wrapper.findAll('button').find(b => ['Save', '✓ Saved'].includes(b.text()))!.trigger('click')
    await flushPromises()
    const body = (fetch.mock.calls.at(-1) as unknown as [string, { body: FormData }])[1].body
    return JSON.parse(String(body.get('layout')))
  }
}

afterEach(() => vi.unstubAllGlobals())

describe('PageBuilderPanel', () => {
  it('saves the layout as it came, without the editor’s node ids, and localized settings in their object form', async () => {
    const save = stubSave()
    const wrapper = mountPanel()
    const saved = await save(wrapper)
    expect(saved.content).toEqual([{
      component: 'T/Components/Group', name: 'group', label: 'Group', custom_name: 'Hero', props: {}, visible: true,
      slots: { default: [{ component: 'T/Components/Heading', name: 'heading', label: 'Heading', props: { text: { default: 'Hi' }, level: 'h1' }, visible: true }] },
    }])
    expect(wrapper.text()).toContain('Layout saved.')
  })

  it('undoes and redoes a change, but leaves the keys alone while a field has focus', async () => {
    const save = stubSave()
    const wrapper = mountPanel()
    await rowOf(wrapper, 'Hero').find('button[title=Hide]').trigger('click')
    expect((await save(wrapper)).content[0].visible).toBe(false)

    ctrl('z')
    await flushPromises()
    expect((await save(wrapper)).content[0].visible).toBe(true)
    ctrl('z', true)
    await flushPromises()
    expect((await save(wrapper)).content[0].visible).toBe(false)

    const input = document.createElement('input')
    document.body.append(input)
    input.dispatchEvent(new KeyboardEvent('keydown', { key: 'z', ctrlKey: true, bubbles: true }))
    await flushPromises()
    expect((await save(wrapper)).content[0].visible).toBe(false)
  })

  it('removes a node after asking', async () => {
    const save = stubSave()
    const wrapper = mountPanel()
    await rowOf(wrapper, 'Hero').find('button[title=Remove]').trigger('click')
    await rowOf(wrapper, 'Hero').findAll('button').find(b => b.text() === 'Yes')!.trigger('click')
    expect((await save(wrapper)).content).toEqual([])
  })

  it('adds a picked component at the end of the zone, with every default it declares', async () => {
    const save = stubSave()
    const wrapper = mountPanel()
    const content = wrapper.findAll('button').filter(b => b.text() === 'Add component')[1]!
    await content.trigger('click')
    const tile = await vi.waitFor(() => {
      const found = wrapper.findAll('.grid button').find(b => b.text() === 'Heading')
      if (!found) throw new Error('picker not loaded')
      return found
    })
    await tile.trigger('click')
    const saved = await save(wrapper)
    expect(saved.content).toHaveLength(2)
    expect(saved.content[1]).toMatchObject({ name: 'heading', props: { text: { default: 'New heading' }, level: 'h2' } })
  })

  it('imports a layout file in place of the current one, and refuses a file that is not one', async () => {
    const save = stubSave()
    const wrapper = mountPanel()
    const input = wrapper.find('input[type=file]')
    // The file is read asynchronously; the notice says when it is done
    const pick = async (text: string, notice: string) => {
      Object.defineProperty(input.element, 'files', { value: [new File([text], 'layout.json')], configurable: true })
      await input.trigger('change')
      await vi.waitFor(() => { if (!wrapper.text().includes(notice)) throw new Error('no notice yet') })
    }
    await pick(JSON.stringify({ content: [{ component: 'T/Components/Heading', name: 'heading', label: 'Heading', props: { text: 'Imported' } }] }), 'Layout imported. Save to keep it.')
    expect((await save(wrapper)).content[0].props.text).toEqual({ default: 'Imported' })

    await pick('[1, 2]', 'That file is not a layout export.')
  })

  it('exports the layout without node ids, named as the server says', () => {
    let blob: Blob | null = null
    const click = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(function (this: HTMLAnchorElement) {
      expect(this.download).toBe('layout.json')
    })
    vi.stubGlobal('URL', Object.assign(URL, { createObjectURL: (b: Blob) => { blob = b; return 'blob:x' }, revokeObjectURL: () => {} }))
    mountPanel()
    usePageBuilderStore().exportLayout()
    expect(click).toHaveBeenCalled()
    return blob!.text().then(text => {
      expect(text).not.toContain('"id"')
      expect(JSON.parse(text).content[0].custom_name).toBe('Hero')
      click.mockRestore()
    })
  })
})
