<script lang="ts">
import { defineComponent, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'

/** One level of the selected node's breadcrumb badge. */
type Crumb = { id: string; label: string }

/**
 * The storefront half of the PageBuilder preview: renders nothing, and talks to the editor in the parent frame
 */
export default defineComponent({
    name: 'PreviewBridge',

    // DynamicNode hands every node a data-lynx-id and a data-lynx-label for the editor to highlight by
    inheritAttrs: false,

    props: {
        // Where the editor is (Observer\AddPreviewBridge): the one origin messages are taken from and sent to
        admin_origin: { type: String, default: '' },
        // What the editor would be opened on to edit this page (Observer\AddPreviewBridge)
        target: { type: [Object, Array], default: null },
        // Where the stylesheet of the editor's unsaved theme is compiled for this page
        theme_url: { type: String, default: '' },
    },

    setup(props) {
        const adminOrigin = props.admin_origin || window.location.origin
        // Framed by the editor; a preview URL opened in a tab of its own has no one to talk to.
        const framed = window.parent !== window
        // The editor's last word on each, as it sent them.
        let latest: { layout: string; theme: string } | null = null
        let outlineZones = ''
        // The theme a page other than the one being edited last had compiled (applyTheme).
        let appliedTheme = ''
        let themeTimer: ReturnType<typeof setTimeout> | null = null
        const THEME_URL = props.theme_url
        let timer: ReturnType<typeof setTimeout> | null = null
        let currentId: string | null = null
        let resizer: ResizeObserver | null = null
        // "Show all outlines": a frame on every node, from the editor's `lynx:outlines`.
        let outlinesOn = false
        let outlinesResizer: ResizeObserver | null = null
        // The selected node's frame, hidden by a press or a key in the page until the editor shows it again
        let dismissed = false
        let stopFinish: (() => void) | null = null
        let stopBefore: (() => void) | null = null

        // The draft belongs to the page the editor is on, which is the one behind the preview route
        const editing = window.location.pathname.includes('/lynx_pagebuilder/')

        // Debounced: the editor rewrites the layout on every keystroke in a settings field
        function draft(): Record<string, string> | null {
            if (!latest?.layout) return null
            return latest.theme ? { layout: latest.layout, theme: latest.theme } : { layout: latest.layout }
        }

        function refresh() {
            if (!editing) return
            const data = draft()
            if (!data) return
            if (timer) clearTimeout(timer)
            timer = setTimeout(() => {
                router.visit(window.location.href, {
                    method: 'post',
                    data,
                    preserveScroll: true,
                    preserveState: true,
                })
            }, 150)
        }

        // A page other than the one being edited is built out of the saved design
        function applyTheme() {
            if (editing || !THEME_URL) return
            const theme = latest?.theme ?? ''
            if (!theme || theme === appliedTheme) return
            if (themeTimer) clearTimeout(themeTimer)
            themeTimer = setTimeout(() => {
                appliedTheme = theme
                fetch(THEME_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                    body: JSON.stringify({ theme }),
                })
                    .then(response => (response.ok ? response.json() : null))
                    .then((data: { css?: unknown } | null) => {
                        // A newer theme arrived while this one was compiled: it has its own request.
                        if (typeof data?.css === 'string' && latest?.theme === theme)
                            router.replaceProp('theme', data.css)
                    })
                    .catch(() => {
                        appliedTheme = ''
                    })
            }, 150)
        }

        /**
         * Carries the draft on the requests the page makes for itself
         */
        function attachDraft(visit: { only?: unknown[]; method?: string; data?: unknown }) {
            if (!editing) return
            // Only a partial reload needs it
            if (!visit.only?.length) return

            const data = draft()
            if (!data) return

            visit.method = 'post'
            visit.data = { ...((visit.data as Record<string, unknown>) ?? {}), ...data }
        }

        function ensureStyle() {
            if (document.getElementById('lynx-highlight-style')) return
            const s = document.createElement('style')
            s.id = 'lynx-highlight-style'
            // The selected node's frame (#lynx-hl) is solid black with a black badge
            s.textContent =
                '#lynx-hl,#lynx-ol,#lynx-ins{position:fixed;inset:0;pointer-events:none}#lynx-hl{z-index:9999}#lynx-ol{z-index:9998}#lynx-ins{z-index:10000}' +
                '.lynx-frame{position:fixed;border:2px solid #09090b;box-shadow:0 0 0 1px #fff,inset 0 0 0 1px #fff;box-sizing:border-box}' +
                '.lynx-frame-badge{position:fixed;background:#09090b;color:#fff;box-shadow:0 0 0 1px #fff;font-size:13px;line-height:1;font-weight:600;padding:4px 9px;white-space:nowrap;font-family:ui-sans-serif,system-ui,sans-serif}' +
                // The boxes take the mouse: with outlines on, the preview is in selection mode and a click anywhere on a node
                '.lynx-ol-box{position:fixed;box-sizing:border-box;border:1px solid transparent;border-image:repeating-linear-gradient(45deg,#09090b 0 3px,#fff 3px 6px) 1;pointer-events:auto;cursor:pointer}' +
                '.lynx-ol-box:hover{border-image:none;border-color:#09090b;background:rgba(9,9,11,.06)}' +
                '.lynx-ol-box.lynx-ol-current{border-image:none;border-color:transparent}.lynx-ol-box.lynx-ol-current:hover{background:none}' +
                // An empty slot's placeholder draws itself; its box only takes the click away from the parent's box under it
                '.lynx-ol-box.lynx-ol-slot{border-image:none;border-color:transparent}.lynx-ol-box.lynx-ol-slot:hover{background:none}' +
                // The selected node's inserters, on a layer over its frame so the frame's line does not cross them
                '.lynx-ins{position:fixed;transform:translate(-50%,-50%);width:24px;height:24px;padding:0;border:none;border-radius:9999px;background:#09090b;color:#fff;box-shadow:0 0 0 2px #fff;display:flex;align-items:center;justify-content:center;pointer-events:auto;cursor:pointer}.lynx-ins:hover{background:#3f3f46}' +
                // A badge per node only on the box under the pointer
                '.lynx-ol-box>.lynx-ol-badge{display:none}.lynx-ol-box:hover>.lynx-ol-badge{display:block}' +
                // The selected node's badge is its path, the ancestors clickable.
                '.lynx-frame-badge{pointer-events:auto}.lynx-crumb{cursor:pointer;opacity:.65}.lynx-crumb:hover{opacity:1;text-decoration:underline}.lynx-crumb-sep{opacity:.5;margin:0 6px}' +
                '.lynx-ol-badge{position:absolute;top:0;left:0;background:#fff;color:#09090b;box-shadow:0 0 0 1px rgba(9,9,11,.7);font:600 12px/1 ui-sans-serif,system-ui,sans-serif;padding:3px 7px;border-radius:0 0 4px 0;white-space:nowrap;pointer-events:auto;cursor:pointer}.lynx-ol-badge:hover{background:#f4f4f5}'
            document.head.appendChild(s)
        }

        function clearHighlight() {
            document.getElementById('lynx-hl')?.remove()
        }

        // Testing the page under the frame; the badge's own crumbs and selection mode keep it
        function dismiss(event: Event) {
            if (!currentId || dismissed || outlinesOn) return
            if (event.target instanceof Element && event.target.closest('#lynx-hl')) return
            dismissed = true
            clearHighlight()
            resizer?.disconnect()
        }

        /**
         * A frame and its badge over `rect`, into `parent`: the badge above the frame when there is room
         */
        function placeFrame(parent: HTMLElement, rect: DOMRect, label: string | null, crumbs: Crumb[] = []) {
            const box = document.createElement('div')
            box.className = 'lynx-frame'
            box.style.cssText = `top:${rect.top}px;left:${rect.left}px;width:${rect.width}px;height:${rect.height}px`
            parent.appendChild(box)
            if (!label) return
            const badge = document.createElement('div')
            badge.className = 'lynx-frame-badge'
            if (crumbs.length > 1) fillCrumbs(badge, crumbs)
            else badge.textContent = label
            parent.appendChild(badge)
            const bh = badge.offsetHeight
            if (rect.top >= bh + 4) {
                badge.style.cssText = `top:${rect.top - bh - 2}px;left:${rect.left}px;border-radius:4px 4px 0 0`
            } else {
                badge.style.cssText = `top:${rect.top + 2}px;left:${rect.left + 2}px;border-radius:0 0 4px 0`
            }
        }

        // The frame and the badge are elements of their own, fixed over the target
        function placeHighlight(el: Element, label: string | null) {
            clearHighlight()
            const wrap = document.createElement('div')
            wrap.id = 'lynx-hl'
            document.body.appendChild(wrap)
            placeFrame(wrap, el.getBoundingClientRect(), label, currentId ? pathTo(currentId) : [])
        }

        /** The nodes from the zone down to `id`, from the draft, named as their badges are. */
        function pathTo(id: string): Crumb[] {
            const walk = (nodes: LayoutItem[], trail: Crumb[]): Crumb[] | null => {
                for (const node of nodes ?? []) {
                    const here = [
                        ...trail,
                        {
                            id: String(node?.id),
                            label:
                                node?.custom_name ||
                                node?.label ||
                                String(node?.component ?? '')
                                    .split('/')
                                    .pop() ||
                                '',
                        },
                    ]
                    if (String(node?.id) === id) return here
                    for (const children of Object.values(node?.slots ?? {})) {
                        const found = walk(children, here)
                        if (found) return found
                    }
                }
                return null
            }
            try {
                const draft = JSON.parse(latest?.layout || '{}') as Record<string, LayoutItem[]>
                for (const zone of Object.values(draft)) {
                    const found = walk(zone, [])
                    if (found) return found
                }
            } catch {
                // No readable draft: the badge is the node's own name.
            }
            return []
        }

        /**
         * The selected node's badge as a breadcrumb, so a parent its children cover whole (a Row) stays reachable
         */
        function fillCrumbs(badge: HTMLElement, crumbs: Crumb[]) {
            const shown = crumbs.slice(-3)
            if (crumbs.length > shown.length) badge.append('… ')
            shown.forEach((crumb, i) => {
                if (i) {
                    const sep = document.createElement('span')
                    sep.className = 'lynx-crumb-sep'
                    sep.textContent = '›'
                    badge.append(sep)
                }
                if (i === shown.length - 1) {
                    badge.append(crumb.label)
                    return
                }
                const link = document.createElement('span')
                link.className = 'lynx-crumb'
                link.textContent = crumb.label
                link.addEventListener('click', event => {
                    event.preventDefault()
                    event.stopPropagation()
                    post({ type: 'lynx:select', id: crumb.id })
                })
                badge.append(link)
            })
        }

        // The element to frame: the one carrying the id, or its first child when it renders as display: contents
        function findTarget(id: string): { el: Element; label: string | null } | null {
            const node = document.querySelector(`[data-lynx-id="${id}"]`)
            if (!node) return null
            const el = getComputedStyle(node).display === 'contents' ? node.firstElementChild : node
            return el ? { el, label: node.getAttribute('data-lynx-label') } : null
        }

        function applyHighlight(id: string | null) {
            clearHighlight()
            resizer?.disconnect()
            if (!id || dismissed) return
            const target = findTarget(id)
            if (!target) return
            target.el.scrollIntoView({ behavior: 'instant' as ScrollBehavior, block: 'nearest' })
            requestAnimationFrame(() => placeHighlight(target.el, target.label))
            // The frame is fixed over where the element was, so a late image or a settings change moves it
            resizer = new ResizeObserver(reposition)
            resizer.observe(target.el)
            resizer.observe(document.body)
        }

        function reposition() {
            placeOutlines()
            if (!currentId || dismissed) return
            const target = findTarget(currentId)
            if (target) placeHighlight(target.el, target.label)
        }

        // One dashed frame per node, its label in the corner
        function clearOutlines() {
            document.getElementById('lynx-ol')?.remove()
            document.getElementById('lynx-ins')?.remove()
        }

        /**
         * The nodes the editor is editing, or null when it is editing every zone
         */
        function outlineableIds(): Set<string> | null {
            const zones = outlineZones.split(',').filter(Boolean)
            if (!zones.length) return null

            const ids = new Set<string>()
            const walk = (nodes: LayoutItem[]): void => {
                for (const node of nodes ?? []) {
                    if (node?.id) ids.add(String(node.id))
                    for (const children of Object.values(node?.slots ?? {})) walk(children)
                }
            }

            try {
                const draft = JSON.parse(latest?.layout || '{}') as Record<string, LayoutItem[]>
                for (const zone of zones) walk(draft[zone])
            } catch {
                // The editor writes it; an unreadable one frames nothing rather than everything.
            }

            return ids
        }

        function placeOutlines() {
            clearOutlines()
            if (!outlinesOn) return
            const allowed = outlineableIds()
            const wrap = document.createElement('div')
            wrap.id = 'lynx-ol'
            document.body.appendChild(wrap)
            // Document order: a parent comes before its children
            document.querySelectorAll('[data-lynx-id]').forEach(node => {
                const id = node.getAttribute('data-lynx-id')
                if (allowed && (!id || !allowed.has(id))) return
                const el = getComputedStyle(node).display === 'contents' ? node.firstElementChild : node
                if (!el) return
                const rect = el.getBoundingClientRect()
                if (!rect.width || !rect.height) return
                const box = document.createElement('div')
                // The selected node gets a box too, or a click on it would fall through to its parent
                box.className = id === currentId ? 'lynx-ol-box lynx-ol-current' : 'lynx-ol-box'
                box.style.cssText = `top:${rect.top}px;left:${rect.left}px;width:${rect.width}px;height:${rect.height}px`
                // A click anywhere on the node tells the editor to select it
                box.addEventListener('click', event => {
                    event.preventDefault()
                    event.stopPropagation()
                    post({ type: 'lynx:select', id })
                })
                const label = node.getAttribute('data-lynx-label')
                if (label && id !== currentId) {
                    const badge = document.createElement('span')
                    badge.className = 'lynx-ol-badge'
                    badge.textContent = label
                    box.appendChild(badge)
                }
                wrap.appendChild(box)
            })
            // Empty slots' placeholders (DynamicNode), last so they sit over their parent's box
            document.querySelectorAll('[data-lynx-slot]').forEach(node => {
                const id = node.getAttribute('data-lynx-slot-of')
                if (allowed && (!id || !allowed.has(id))) return
                const rect = node.getBoundingClientRect()
                if (!rect.width || !rect.height) return
                const box = document.createElement('div')
                box.className = 'lynx-ol-box lynx-ol-slot'
                box.style.cssText = `top:${rect.top}px;left:${rect.left}px;width:${rect.width}px;height:${rect.height}px`
                box.addEventListener('click', event => {
                    event.preventDefault()
                    event.stopPropagation()
                    post({ type: 'lynx:add', id, slot: node.getAttribute('data-lynx-slot') })
                })
                wrap.appendChild(box)
            })

            if (currentId && (!allowed || allowed.has(currentId))) {
                const target = findTarget(currentId)
                if (target) placeInserters(target.el, currentId)
            }
        }

        /** The visible nodes on either side of `id` in its container, from the draft. */
        function neighbours(id: string): { prev?: string; next?: string } {
            let found: { prev?: string; next?: string } | null = null
            const walk = (nodes: LayoutItem[]): void => {
                const list = (nodes ?? []).filter(node => node?.visible !== false)
                for (let i = 0; i < list.length && !found; i++) {
                    if (String(list[i]?.id) === id) {
                        found = {
                            prev: list[i - 1]?.id && String(list[i - 1].id),
                            next: list[i + 1]?.id && String(list[i + 1].id),
                        }
                    } else {
                        for (const children of Object.values(list[i]?.slots ?? {})) walk(children)
                    }
                }
            }
            try {
                const draft = JSON.parse(latest?.layout || '{}') as Record<string, LayoutItem[]>
                for (const zone of Object.values(draft)) {
                    walk(zone)
                    if (found) break
                }
            } catch {
                // No readable draft: no neighbours, the container decides.
            }
            return found ?? {}
        }

        /**
         * Which way the node's container runs, read from where its neighbours are drawn rather than from the container's
         */
        function flowOf(el: Element, id: string): { row: boolean; reverse: boolean } {
            const rect = el.getBoundingClientRect()
            const { prev, next } = neighbours(id)
            for (const [sibling, isPrev] of [
                [prev, true],
                [next, false],
            ] as const) {
                const other = sibling ? findTarget(sibling)?.el.getBoundingClientRect() : null
                if (!other || !other.width || !other.height) continue
                const beside =
                    other.top < rect.bottom &&
                    other.bottom > rect.top &&
                    (other.right <= rect.left + 1 || other.left >= rect.right - 1)
                return beside
                    ? { row: true, reverse: isPrev ? other.left > rect.left : other.left < rect.left }
                    : { row: false, reverse: isPrev ? other.top > rect.top : other.top < rect.top }
            }

            let parent = el.parentElement
            while (parent && getComputedStyle(parent).display === 'contents') parent = parent.parentElement
            if (!parent) return { row: false, reverse: false }
            const style = getComputedStyle(parent)
            if (!style.display.includes('flex')) return { row: false, reverse: false }
            const dir = style.flexDirection
            return dir.startsWith('row')
                ? { row: true, reverse: (dir === 'row-reverse') !== (style.direction === 'rtl') }
                : { row: false, reverse: dir === 'column-reverse' }
        }

        /**
         * A + on either side of the selected node, to add a component right before or after it in the same container
         */
        function placeInserters(el: Element, id: string) {
            const rect = el.getBoundingClientRect()
            if (!rect.width || !rect.height) return
            const { row, reverse } = flowOf(el, id)
            const midX = rect.left + rect.width / 2
            const midY = rect.top + rect.height / 2
            const ends = row
                ? [
                      { x: rect.left, y: midY },
                      { x: rect.right, y: midY },
                  ]
                : [
                      { x: midX, y: rect.top },
                      { x: midX, y: rect.bottom },
                  ]
            if (reverse) ends.reverse()
            const layer = document.createElement('div')
            layer.id = 'lynx-ins'
            document.body.appendChild(layer)
            ;(['before', 'after'] as const).forEach((position, i) => {
                const button = document.createElement('button')
                button.type = 'button'
                button.className = 'lynx-ins'
                button.title = position === 'before' ? 'Add before' : 'Add after'
                button.innerHTML =
                    '<svg width="12" height="12" viewBox="0 0 12 12" aria-hidden="true"><path d="M6 1v10M1 6h10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>'
                button.style.cssText = `top:${ends[i].y}px;left:${ends[i].x}px`
                button.addEventListener('click', event => {
                    event.preventDefault()
                    event.stopPropagation()
                    post({ type: 'lynx:insert', id, position })
                })
                layer.appendChild(button)
            })
        }

        // A click on an empty slot's placeholder asks the editor to open its picker for that slot
        function onSlotClick(event: MouseEvent) {
            const node = (event.target as Element | null)?.closest?.('[data-lynx-slot]')
            if (!node) return
            event.preventDefault()
            event.stopPropagation()
            post({
                type: 'lynx:add',
                id: node.getAttribute('data-lynx-slot-of'),
                slot: node.getAttribute('data-lynx-slot'),
            })
        }

        // Empty slots' placeholders (DynamicNode) are drawn only while the outlines are on
        function showSlots(on: boolean) {
            router.replaceProp('preview_outlines', on, { onFinish: () => requestAnimationFrame(placeOutlines) })
        }

        function applyOutlines(on: boolean) {
            outlinesOn = on
            showSlots(on)
            outlinesResizer?.disconnect()
            outlinesResizer = null
            placeOutlines()
            if (!on) return
            outlinesResizer = new ResizeObserver(placeOutlines)
            outlinesResizer.observe(document.body)
        }

        function post(message: Record<string, unknown>) {
            window.parent.postMessage(message, adminOrigin)
        }

        function onMessage(e: MessageEvent) {
            if (e.source !== window.parent) return
            if (e.origin !== adminOrigin) return
            const data = e.data as {
                type?: string
                id?: string | null
                zones?: string
                on?: boolean
                layout?: unknown
                theme?: unknown
            } | null
            switch (data?.type) {
                case 'lynx:highlight':
                    currentId = data.id ?? null
                    dismissed = false
                    applyHighlight(currentId)
                    // The outlines mark the selected node and carry its inserters.
                    placeOutlines()
                    break
                case 'lynx:outlines':
                    outlineZones = String(data.zones ?? '')
                    applyOutlines(data.on === true)
                    break
                case 'lynx:draft':
                    if (typeof data.layout !== 'string') return
                    latest = { layout: data.layout, theme: typeof data.theme === 'string' ? data.theme : '' }
                    // A settings change shows the node again once the preview has redrawn it
                    dismissed = false
                    refresh()
                    applyTheme()
                    break
            }
        }

        onMounted(() => {
            ensureStyle()
            window.addEventListener('message', onMessage)
            document.addEventListener('click', onSlotClick, true)
            document.addEventListener('scroll', reposition, true)
            document.addEventListener('pointerdown', dismiss, true)
            document.addEventListener('keydown', dismiss, true)
            window.addEventListener('resize', reposition)
            stopFinish = router.on('finish', () => {
                if (outlinesOn) showSlots(true)
                if (currentId) applyHighlight(currentId)
                placeOutlines()
            })
            // Mutating the visit in 'before' is how it reaches the request
            stopBefore = router.on('before', event => {
                const { url, method, only } = event.detail.visit
                // A plain reload of this page would drop the draft; a sample page's '#' links are such reloads
                if (
                    editing &&
                    method === 'get' &&
                    !only.length &&
                    url.pathname + url.search === window.location.pathname + window.location.search
                )
                    return false
                attachDraft(event.detail.visit)
            })
            // The editor answers with the draft, the outlines and the highlight
            if (framed)
                post({
                    type: 'lynx:ready',
                    editing,
                    target: JSON.parse(JSON.stringify(props.target ?? null)),
                    href: window.location.href,
                })
        })

        onUnmounted(() => {
            window.removeEventListener('message', onMessage)
            document.removeEventListener('click', onSlotClick, true)
            document.removeEventListener('scroll', reposition, true)
            document.removeEventListener('pointerdown', dismiss, true)
            document.removeEventListener('keydown', dismiss, true)
            window.removeEventListener('resize', reposition)
            stopFinish?.()
            stopBefore?.()
            resizer?.disconnect()
            outlinesResizer?.disconnect()
            clearHighlight()
            clearOutlines()
            if (timer) clearTimeout(timer)
            if (themeTimer) clearTimeout(themeTimer)
        })

        return () => null
    },
})
</script>
