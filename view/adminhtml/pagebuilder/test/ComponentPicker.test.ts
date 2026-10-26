import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import ComponentPicker from '@/components/ComponentPicker.vue'

const COMPONENTS = [
  { name: 'heading', key: 'A/Components/Heading', label: 'Heading', module: 'A', group: 'text' },
  { name: 'logo', key: 'A/Components/Logo', label: 'Logo', module: 'A', group: 'header_footer', zones: { header: 'header' } },
  { name: 'tag', key: 'B/Components/GoogleTag', label: 'Google Tag', module: 'B', group: 'general', zones: { backstage: 'backstage' } },
  { name: 'button', key: 'A/Components/Button', label: 'Button', module: 'A', group: 'general' },
]

const tiles = (wrapper: ReturnType<typeof mount>) => wrapper.findAll('.grid button').map(b => b.text())
const headings = (wrapper: ReturnType<typeof mount>) => wrapper.findAll('h3').map(h => h.text())

describe('ComponentPicker', () => {
  it('offers what the zone takes, grouped with General last', () => {
    const wrapper = mount(ComponentPicker, { props: { components: COMPONENTS, targetZone: 'content' } })
    expect(tiles(wrapper)).toEqual(['Heading', 'Button'])
    expect(headings(wrapper)).toEqual(['Text', 'General'])
  })

  it('gives backstage only the components that name it', () => {
    const wrapper = mount(ComponentPicker, { props: { components: COMPONENTS, targetZone: 'backstage' } })
    expect(tiles(wrapper)).toEqual(['Google Tag'])
  })

  it('holds a slot to its allowed groups and locks what it holds as often as it may', () => {
    const wrapper = mount(ComponentPicker, { props: { components: COMPONENTS, targetZone: 'header', allowedGroups: ['header_footer', 'text'], fullComponents: ['logo'] } })
    // Groups sort by name: Header Footer before Text
    expect(tiles(wrapper)).toEqual(['LogoAlready added', 'Heading'])
    const logo = wrapper.findAll('.grid button').find(b => b.text().startsWith('Logo'))!
    expect(logo.attributes('disabled')).toBeDefined()
  })

  it('filters by name or module, and picks', async () => {
    const wrapper = mount(ComponentPicker, { props: { components: COMPONENTS, targetZone: 'content' } })
    await wrapper.find('input').setValue('butt')
    expect(tiles(wrapper)).toEqual(['Button'])
    await wrapper.find('.grid button').trigger('click')
    expect(wrapper.emitted('select')![0]![0]).toMatchObject({ name: 'button' })
    expect(wrapper.emitted('close')).toHaveLength(1)
  })

  it('closes on Escape', () => {
    const wrapper = mount(ComponentPicker, { props: { components: COMPONENTS } })
    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))
    expect(wrapper.emitted('close')).toHaveLength(1)
  })
})
