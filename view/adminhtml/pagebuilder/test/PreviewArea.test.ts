import { flushPromises, mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import PreviewArea from '@/components/PreviewArea.vue'
import { usePageBuilderStore } from '@/stores/pageBuilder'
import { usePreviewStore } from '@/stores/preview'

const BASE = 'https://shop.test/'
const CONFIG = {
  previewUrl: `${BASE}lynx_pagebuilder/`,
  previewRoute: 'cms/page/view/id/2',
  previewToken: 'tok',
  frameEditorUrl: 'https://admin.test/editor/key/abc/',
  framePages: [{ code: 'category', label: 'Category page', path: 'catalog_category_view' }],
  currentTarget: { page: 'home' },
}

function mountArea(config = {}) {
  usePageBuilderStore().init({ ...CONFIG, ...config })
  const wrapper = mount(PreviewArea, { attachTo: document.body })
  // The frame never loads here: a stand-in window is what the editor talks to and hears from
  const frameWindow = { postMessage: vi.fn() }
  Object.defineProperty(wrapper.find('iframe').element, 'contentWindow', { value: frameWindow })
  const fromFrame = async (data: unknown) => {
    const event = new MessageEvent('message', { data, origin: 'https://shop.test' })
    Object.defineProperty(event, 'source', { value: frameWindow })
    window.dispatchEvent(event)
    await flushPromises()
  }
  return { wrapper, frame: wrapper.find('iframe').element as HTMLIFrameElement, frameWindow, fromFrame }
}

const offers = (wrapper: ReturnType<typeof mount>) => wrapper.findAll('button').filter(b => b.text().startsWith('Edit')).map(b => b.text())

describe('PreviewArea', () => {
  it('points the frame at the preview route with the token and store', () => {
    const { frame } = mountArea({ previewStoreCode: 'de' })
    expect(frame.getAttribute('src')).toBe(`${BASE}lynx_pagebuilder/cms/page/view/id/2?token=tok&___store=de`)
  })

  it('passes the frame’s picks on, and only from its own frame', async () => {
    const { fromFrame } = mountArea()
    const store = usePageBuilderStore()
    const select = vi.spyOn(store, 'selectFromPreview').mockImplementation(() => {})
    const add = vi.spyOn(store, 'addFromPreview').mockImplementation(() => {})
    const insert = vi.spyOn(store, 'insertFromPreview').mockImplementation(() => {})
    await fromFrame({ type: 'lynx:select', id: 7 })
    await fromFrame({ type: 'lynx:add', id: 3, slot: 'default' })
    await fromFrame({ type: 'lynx:insert', id: 3, position: 'after' })
    await fromFrame({ type: 'lynx:insert', id: 3, position: 'inside' })
    window.dispatchEvent(new MessageEvent('message', { data: { type: 'lynx:select', id: 9 }, origin: 'https://shop.test' }))
    expect(select.mock.calls).toEqual([['7']])
    expect(add.mock.calls).toEqual([[{ id: '3', slot: 'default' }]])
    expect(insert.mock.calls).toEqual([[{ id: '3', position: 'after' }]])
  })

  it('marks the frame away while it shows another page', async () => {
    const { fromFrame } = mountArea()
    await fromFrame({ type: 'lynx:ready', editing: false })
    expect(usePreviewStore().frameAway).toBe(true)
    await fromFrame({ type: 'lynx:ready', editing: true })
    expect(usePreviewStore().frameAway).toBe(false)
  })

  it('offers a category both its own layout and the shared page, the one the storefront shows marked live', async () => {
    const { wrapper, fromFrame } = mountArea()
    await fromFrame({
      type: 'lynx:ready',
      editing: false,
      href: `${BASE}gear.html?token=tok`,
      target: { path: 'catalog_category_view', params: { category: 7 }, label: 'Gear', uses_own: true },
    })
    expect(wrapper.text()).toContain('Viewing Gear')
    expect(offers(wrapper)).toEqual(['Edit Category page', 'Edit Gear onlylive'])
  })

  it('keeps the shared page on offer while the editor is on it, reopened on the frame’s route', async () => {
    const assign = vi.spyOn(window.location, 'assign').mockImplementation(() => {})
    const { wrapper, fromFrame } = mountArea({ currentTarget: { page: 'category' } })
    await fromFrame({ type: 'lynx:ready', editing: false, href: `${BASE}gear.html?token=tok&___store=de`, target: { path: 'catalog_category_view', params: { category: 7 }, label: 'Gear' } })
    // The category has no layout of its own on show: the shared page is the live one
    expect(offers(wrapper)).toEqual(['Edit Category pagelive', 'Edit Gear only'])
    await wrapper.findAll('button').find(b => b.text().startsWith('Edit Category page'))!.trigger('click')
    // The confirm loads on first use
    const confirm = await vi.waitFor(() => {
      const found = [...document.body.querySelectorAll('[data-slot=alert-dialog-footer] button')].find(b => b.textContent === 'Edit Category page')
      if (!found) throw new Error('not yet')
      return found as HTMLButtonElement
    })
    confirm.click()
    expect(assign).toHaveBeenCalledWith('https://admin.test/editor/key/abc/?page=category&preview_route=gear.html')
    assign.mockRestore()
  })

  it('offers nothing while the frame is on the page being edited, or for the layout already open', async () => {
    const { wrapper, fromFrame } = mountArea({ currentTarget: { params: { cms_page: '2' } } })
    await fromFrame({ type: 'lynx:ready', editing: true, target: { params: { cms_page: 5 } } })
    expect(offers(wrapper)).toEqual([])
    await fromFrame({ type: 'lynx:ready', editing: false, href: BASE, target: { params: { cms_page: 2 }, label: 'About' } })
    expect(offers(wrapper)).toEqual([])
    await fromFrame({ type: 'lynx:ready', editing: false, href: BASE, target: { params: { cms_page: 5 }, label: 'Contact' } })
    expect(offers(wrapper)).toEqual(['Edit this page'])
  })

  it('answers the frame’s ready with the outlines, the highlight and the draft', async () => {
    const { frameWindow, fromFrame } = mountArea({ layout: { content: [{ component: 'T/Components/Heading', name: 'heading' }] } })
    const store = usePageBuilderStore()
    store.selectNode(store.layout.content![0]!)
    usePreviewStore().outlines = true
    await flushPromises()
    frameWindow.postMessage.mockClear()
    await fromFrame({ type: 'lynx:ready', editing: true })
    const post = frameWindow.postMessage
    const types = post.mock.calls.map(([message]) => (message as { type: string }).type)
    expect(types).toEqual(['lynx:outlines', 'lynx:highlight', 'lynx:draft'])
    expect(post.mock.calls[1]![0]).toEqual({ type: 'lynx:highlight', id: store.selectedNode!.id })
  })
})
