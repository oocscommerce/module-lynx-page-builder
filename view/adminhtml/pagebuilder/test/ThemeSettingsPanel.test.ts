import { flushPromises, mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import { reactive } from 'vue'
import ThemeSettingsPanel from '@/components/ThemeSettingsPanel.vue'
import { TooltipProvider } from '@/ui/tooltip'

const FIELDS = {
  color_primary: { group: 'Colors', type: 'color', label: 'Primary', var: '--color-primary' },
  radius_box: { group: 'Shape', type: 'select', label: 'Box', var: '--radius-box', options: { none: { value: 'none', label: 'None' }, md: { value: 'md', label: 'Medium' } }, values: { none: '0px', md: '0.5rem' } },
  radius_button: { group: 'Shape', type: 'select', label: 'Button', var: '--radius-button', options: { '0.25rem': { value: '0.25rem', label: 'S' }, '0.5rem': { value: '0.5rem', label: 'M' } } },
  border: { group: 'Shape', type: 'number', label: 'Border', var: '--border', min: 0, max: 4 },
  color_link: { group: 'Colors', type: 'color', label: 'Link', var: '--color-link' },
  custom_css: { group: 'Advanced', type: 'textarea', label: 'Custom CSS' },
}

function mountTheme(values: Record<string, string>, saved = { ...values }) {
  const reactiveValues = reactive({ ...values })
  const wrapper = mount(
    { components: { ThemeSettingsPanel, TooltipProvider }, setup: () => ({ values: reactiveValues, fields: FIELDS, saved }), template: '<TooltipProvider><ThemeSettingsPanel :fields="fields" :values="values" :saved="saved" /></TooltipProvider>' },
    { global: { provide: { fieldComponents: {} } }, attachTo: document.body },
  )
  return { wrapper, values: reactiveValues }
}
const button = (wrapper: ReturnType<typeof mount>, text: string) => wrapper.findAll('button').find(b => b.text() === text)!

async function importTheme(wrapper: ReturnType<typeof mount>, text: string) {
  await wrapper.findAll('[data-slot=accordion-trigger]').find(t => t.text().includes('Import'))!.trigger('click')
  await flushPromises()
  await wrapper.find('textarea').setValue(text)
  await button(wrapper, 'Import').trigger('click')
}

describe('ThemeSettingsPanel', () => {
  it('lists one accordion row per group, in the order the fields first name them', () => {
    const { wrapper } = mountTheme({})
    expect(wrapper.findAll('[data-slot=accordion-trigger]').map(t => t.text())).toEqual(['Import daisyUI theme', 'Colors', 'Shape', 'Advanced'])
  })

  it('reads a pasted daisyUI theme into the settings, and changes nothing until Apply', async () => {
    const { wrapper, values } = mountTheme({ color_primary: '#000', radius_box: 'none', radius_button: '0.25rem', border: '1', custom_css: '' })
    await importTheme(wrapper, `@plugin "daisyui/theme" {
      name: "mine";
      --color-primary: oklch(58% 0.2 277);
      --radius-box: 0.5rem;
      --radius-field: 8px;
      --border: 2px;
      --depth: 1;
      --radius-selector: 1.5rem;
    }`)
    expect(wrapper.text()).toContain('5 setting updated')
    // No setting owns --radius-field: it goes to Custom CSS and still sets the button below
    expect(wrapper.text()).toContain('3 added to Custom CSS: --radius-field, --depth, --radius-selector')
    expect(values.color_primary).toBe('#000')

    await button(wrapper, 'Apply').trigger('click')
    // rem and px compare by length; --radius-field carries over to the button, --color-primary to the link
    expect(values).toMatchObject({
      color_primary: 'oklch(58% 0.2 277)',
      color_link: 'oklch(58% 0.2 277)',
      radius_box: 'md',
      radius_button: '0.5rem',
      border: '2',
    })
    expect(values.custom_css).toBe('/* imported */\n:root{--radius-field:8px;--depth:1;--radius-selector:1.5rem}')
  })

  it('names what a setting cannot take instead of importing it', async () => {
    const { wrapper } = mountTheme({ radius_box: 'none' })
    await importTheme(wrapper, '--radius-box: 3rem;')
    expect(wrapper.text()).toContain('1 not imported, no matching option: --radius-box: 3rem')
  })

  it('discards a draft, and resets to what the design has saved', async () => {
    const { wrapper, values } = mountTheme({ border: '1' }, { border: '3' })
    await importTheme(wrapper, '--border: 2px;')
    await button(wrapper, 'Discard').trigger('click')
    await button(wrapper, 'Apply').trigger('click')
    expect(values.border).toBe('1')

    await button(wrapper, 'Reset').trigger('click')
    await button(wrapper, 'Apply').trigger('click')
    expect(values.border).toBe('3')
  })
})
