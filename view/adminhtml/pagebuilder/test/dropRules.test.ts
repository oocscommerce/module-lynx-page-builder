import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it } from 'vitest'
import Draggable from 'vuedraggable'
import PageSection from '@/components/PageSection.vue'
import TreeNode from '@/components/TreeNode.vue'
import { usePageBuilderStore } from '@/stores/pageBuilder'

const REGISTRY: Record<string, Record<string, unknown>> = {
  row: { slots: { default: { label: 'Columns', allowed_groups: { layout: 'layout' }, max_per_component: 2 } } },
  column: { group: 'layout' },
  heading: { group: 'text' },
  logo: { zones: { header: 'header' } },
  tag: { zones: { backstage: 'backstage' } },
}
function dragged(name: string) {
  const el = document.createElement('div')
  el.dataset.componentKey = `X/${name}`
  el.dataset.componentName = name
  return el
}

beforeEach(() => {
  const components = Object.entries(REGISTRY).map(([name, def]) => ({ name, key: `X/${name}`, ...def }))
  usePageBuilderStore().init({ components, registeredComponents: Object.keys(REGISTRY) })
})

describe('drop rules', () => {
  it('a slot takes only its allowed groups, and no more of one component than it allows', () => {
    const node = { id: '1', key: 'X/row', name: 'row', label: 'Row', slots: { default: [{ id: '2', key: 'X/column', name: 'column', label: 'Column' }] } }
    const wrapper = mount(TreeNode, { props: { node } })
    // A row starts folded; its slot list renders once unfolded
    return wrapper.find('button[title=Expand]').trigger('click').then(() => {
      const put = (wrapper.findComponent(Draggable).vm.$attrs.group as { put: unknown }).put as (to: unknown, from: unknown, el: HTMLElement) => boolean
      const here = {}
      expect(put(here, {}, dragged('heading'))).toBe(false)
      expect(put(here, {}, dragged('column'))).toBe(true)
      node.slots.default.push({ id: '3', key: 'X/column', name: 'column', label: 'Column' })
      expect(put(here, {}, dragged('column'))).toBe(false)
      // Reordering inside the slot never counts as one more
      expect(put(here, here, dragged('column'))).toBe(true)
    })
  })

  it('a zone takes components without zones, those that name it, and backstage only its own', () => {
    const zone = (zoneKey: string) => mount(PageSection, { props: { zoneKey, label: zoneKey } })
    const put = (zoneKey: string) => (zone(zoneKey).findComponent(Draggable).vm.$attrs.group as { put: unknown }).put as (to: unknown, from: unknown, el: HTMLElement) => boolean
    expect(put('header')({}, {}, dragged('heading'))).toBe(true)
    expect(put('header')({}, {}, dragged('logo'))).toBe(true)
    expect(put('header')({}, {}, dragged('tag'))).toBe(false)
    expect(put('backstage')({}, {}, dragged('heading'))).toBe(false)
    expect(put('backstage')({}, {}, dragged('tag'))).toBe(true)
  })
})
