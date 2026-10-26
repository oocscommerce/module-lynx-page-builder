import { router } from '@inertiajs/vue3'
import { mountNode } from '@oocs/module-lynx-theme/test'
import DynamicNode from 'OOCS_LynxTheme/Components/DynamicNode.vue'
import PreviewBridge from 'OOCS_LynxPageBuilder/Components/PreviewBridge.vue'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { defineComponent, h } from 'vue'

const ADMIN = 'https://admin.shop.test'
const EDITING_PATH = '/lynx_pagebuilder/preview/index/'
// What Observer\AddPreviewBridge hands the bridge
const props = { admin_origin: ADMIN, target: { type: 'page', id: 'home' }, theme_url: '' }

const heading = {
    id: 'h1',
    component: 'OOCS_LynxThemeComponent/Components/Heading',
    label: 'Heading',
    custom_name: 'Hero title',
    props: { text: 'Welcome', level: 'h2', text_align: 'left', color: '' },
}
const layout = {
    content: [
        {
            id: 'g1',
            component: 'OOCS_LynxThemeComponent/Components/Group',
            label: 'Group',
            props: {},
            slots: { default: [heading] },
        },
    ],
}

// The editor frame around the preview
const editor = { postMessage: vi.fn() }
const frame = () => vi.spyOn(window, 'parent', 'get').mockReturnValue(editor as unknown as Window)
const send = (data: unknown, origin = ADMIN, source: unknown = editor) =>
    window.dispatchEvent(new MessageEvent('message', { data, origin, source: source as Window }))
const posted = () => editor.postMessage.mock.calls.map(([message, origin]) => ({ ...message, origin }))

function mount(options: Parameters<typeof mountNode>[1] = {}) {
    const wrapper = mountNode(PreviewBridge, { props, page: { preview: true }, ...options })
    return wrapper
}

/** A node and the bridge on one preview page, as the storefront renders them. */
const PreviewPage = defineComponent({
    setup: () => () => [h(DynamicNode, { item: heading }), h(PreviewBridge, props)],
})

describe('PreviewBridge', () => {
    afterEach(() => {
        editor.postMessage.mockReset()
        document.getElementById('lynx-highlight-style')?.remove()
        window.history.replaceState({}, '', '/')
    })

    it('renders nothing and adds the highlight styles', () => {
        const wrapper = mount()

        expect(wrapper.html()).toBe('')
        expect(document.getElementById('lynx-highlight-style')).not.toBeNull()
    })

    it('tells the editor it is ready when framed', () => {
        window.history.replaceState({}, '', EDITING_PATH)
        frame()
        mount()

        expect(posted()).toEqual([
            {
                type: 'lynx:ready',
                editing: true,
                target: { type: 'page', id: 'home' },
                href: `${window.location.origin}${EDITING_PATH}`,
                origin: ADMIN,
            },
        ])
    })

    it('stays silent in a tab of its own', () => {
        const postMessage = vi.spyOn(window, 'postMessage')
        mount()

        expect(postMessage).not.toHaveBeenCalled()
    })

    it('reloads the page with the latest draft while editing', () => {
        vi.useFakeTimers()
        window.history.replaceState({}, '', EDITING_PATH)
        frame()
        const visit = vi.spyOn(router, 'visit').mockImplementation(() => {})
        mount()

        send({ type: 'lynx:draft', layout: '{"content":[]}', theme: '' })
        send({ type: 'lynx:draft', layout: JSON.stringify(layout), theme: '{"primary":"#0055ff"}' })
        vi.advanceTimersByTime(150)

        expect(visit).toHaveBeenCalledTimes(1)
        expect(visit).toHaveBeenCalledWith(window.location.href, {
            method: 'post',
            data: { layout: JSON.stringify(layout), theme: '{"primary":"#0055ff"}' },
            preserveScroll: true,
            preserveState: true,
        })
    })

    it('ignores messages from another origin or window', () => {
        vi.useFakeTimers()
        window.history.replaceState({}, '', EDITING_PATH)
        frame()
        const visit = vi.spyOn(router, 'visit').mockImplementation(() => {})
        mount()

        send({ type: 'lynx:draft', layout: '{}' }, 'https://evil.test')
        send({ type: 'lynx:draft', layout: '{}' }, ADMIN, window)
        vi.advanceTimersByTime(500)

        expect(visit).not.toHaveBeenCalled()
    })

    it('compiles the draft theme for a page not being edited', async () => {
        frame()
        const fetch = vi.fn().mockResolvedValue(new Response(JSON.stringify({ css: ':root{--color-primary:#0055ff}' })))
        vi.stubGlobal('fetch', fetch)
        const replaceProp = vi.spyOn(router, 'replaceProp').mockImplementation(() => {})
        const visit = vi.spyOn(router, 'visit').mockImplementation(() => {})
        mount({ props: { ...props, theme_url: 'https://shop.test/lynx_pagebuilder/theme/css/' } })

        send({ type: 'lynx:draft', layout: '{}', theme: '{"primary":"#0055ff"}' })

        await vi.waitFor(() => expect(replaceProp).toHaveBeenCalledWith('theme', ':root{--color-primary:#0055ff}'))
        expect(fetch).toHaveBeenCalledWith('https://shop.test/lynx_pagebuilder/theme/css/', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({ theme: '{"primary":"#0055ff"}' }),
        })
        expect(visit).not.toHaveBeenCalled()
    })

    it('shows the empty slots while the outlines are on', () => {
        frame()
        const replaceProp = vi.spyOn(router, 'replaceProp').mockImplementation(() => {})
        mount()

        send({ type: 'lynx:outlines', on: true, zones: '' })

        expect(replaceProp).toHaveBeenCalledWith('preview_outlines', true, expect.anything())
        expect(document.getElementById('lynx-ol')).not.toBeNull()
    })

    it('asks the editor to fill an empty slot clicked', () => {
        frame()
        mount()
        const slot = document.createElement('div')
        slot.dataset.lynxSlot = 'default'
        slot.dataset.lynxSlotOf = 'g1'
        document.body.append(slot)

        slot.click()

        expect(posted()).toContainEqual({ type: 'lynx:add', id: 'g1', slot: 'default', origin: ADMIN })
    })

    it('keeps the draft on the page requests while editing', () => {
        window.history.replaceState({}, '', EDITING_PATH)
        frame()
        mount()
        send({ type: 'lynx:draft', layout: '{"content":[]}', theme: '' })
        const before = (visit: Record<string, unknown>) => {
            const event = new CustomEvent('inertia:before', { detail: { visit }, cancelable: true })
            document.dispatchEvent(event)
            return event
        }

        const reload = { url: new URL(window.location.href), method: 'get', only: [], data: {} }
        expect(before(reload).defaultPrevented).toBe(true)

        const partial = { url: new URL(window.location.href), method: 'get', only: ['product_list'], data: { p: 2 } }
        expect(before(partial).defaultPrevented).toBe(false)
        expect(partial).toMatchObject({ method: 'post', data: { p: 2, layout: '{"content":[]}' } })
    })

    it('frames the node the editor highlights, its parents clickable', async () => {
        frame()
        mountNode(PreviewPage, { page: { preview: true }, attachTo: document.body })
        await vi.waitFor(() => expect(document.querySelector('[data-lynx-id="h1"]')).not.toBeNull())

        send({ type: 'lynx:draft', layout: JSON.stringify(layout), theme: '' })
        send({ type: 'lynx:highlight', id: 'h1' })

        await vi.waitFor(() => expect(document.querySelector('#lynx-hl .lynx-frame-badge')).not.toBeNull())
        expect(document.querySelector('#lynx-hl .lynx-frame-badge')!.textContent).toBe('Group›Hero title')
        ;(document.querySelector('#lynx-hl .lynx-crumb') as HTMLElement).click()
        expect(posted()).toContainEqual({ type: 'lynx:select', id: 'g1', origin: ADMIN })
    })

    it('hides the frame on a press or a key in the page until the editor shows it again', async () => {
        frame()
        vi.spyOn(router, 'replaceProp').mockImplementation(() => {})
        const wrapper = mountNode(PreviewPage, { page: { preview: true }, attachTo: document.body })
        await vi.waitFor(() => expect(document.querySelector('[data-lynx-id="h1"]')).not.toBeNull())
        const press = (el: Element) => el.dispatchEvent(new Event('pointerdown', { bubbles: true }))
        const finish = () => document.dispatchEvent(new CustomEvent('inertia:finish', { detail: { visit: {} } }))
        const shown = () => vi.waitFor(() => expect(document.getElementById('lynx-hl')).not.toBeNull())

        send({ type: 'lynx:highlight', id: 'h1' })
        await shown()
        press(document.querySelector('#lynx-hl .lynx-frame-badge')!)
        expect(document.getElementById('lynx-hl')).not.toBeNull()

        press(document.querySelector('h2')!)
        expect(document.getElementById('lynx-hl')).toBeNull()
        window.dispatchEvent(new Event('resize'))
        finish()
        await new Promise(resolve => requestAnimationFrame(resolve))
        expect(document.getElementById('lynx-hl')).toBeNull()

        // A settings change, then the redrawn page
        send({ type: 'lynx:draft', layout: JSON.stringify(layout), theme: '' })
        finish()
        await shown()

        document.body.dispatchEvent(new KeyboardEvent('keydown', { bubbles: true }))
        expect(document.getElementById('lynx-hl')).toBeNull()
        send({ type: 'lynx:highlight', id: 'h1' })
        await shown()

        // Selection mode: a press picks a node, the frame stays
        send({ type: 'lynx:outlines', on: true, zones: '' })
        press(document.querySelector('h2')!)
        expect(document.getElementById('lynx-hl')).not.toBeNull()
        wrapper.unmount()
    })

    it('takes its frames and listeners away when unmounted', async () => {
        frame()
        const visit = vi.spyOn(router, 'visit').mockImplementation(() => {})
        const wrapper = mountNode(PreviewPage, { page: { preview: true }, attachTo: document.body })
        await vi.waitFor(() => expect(document.querySelector('[data-lynx-id="h1"]')).not.toBeNull())
        send({ type: 'lynx:highlight', id: 'h1' })
        await vi.waitFor(() => expect(document.getElementById('lynx-hl')).not.toBeNull())

        wrapper.unmount()
        send({ type: 'lynx:highlight', id: 'h1' })
        send({ type: 'lynx:draft', layout: '{}' })

        expect(document.getElementById('lynx-hl')).toBeNull()
        expect(visit).not.toHaveBeenCalled()
    })
})
