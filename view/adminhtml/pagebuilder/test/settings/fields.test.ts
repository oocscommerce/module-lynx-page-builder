import { flushPromises, mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import FieldNumber from '@/components/settings/FieldNumber.vue'
import FieldSelect from '@/components/settings/FieldSelect.vue'
import FieldText from '@/components/settings/FieldText.vue'
import FieldToggle from '@/components/settings/FieldToggle.vue'
import FieldUrl from '@/components/settings/FieldUrl.vue'
import { Select } from '@/ui/select'
import { Slider } from '@/ui/slider'
import { Switch } from '@/ui/switch'

const OPTIONS = {
  none: { value: '', label: 'None' },
  small: { value: 'small', label: 'Small' },
  large: { value: 'large', label: 'Large' },
}

describe('FieldText', () => {
  it('emits what is typed', async () => {
    const wrapper = mount(FieldText, { props: { value: 'a', def: { placeholder: 'Title' } } })
    await wrapper.find('input').setValue('abc')
    expect(wrapper.emitted('update')!.at(-1)).toEqual(['abc'])
    expect(wrapper.find('input').attributes('placeholder')).toBe('Title')
  })
})

describe('FieldSelect', () => {
  it('carries an empty option under a stand-in value, since the select refuses an empty one', async () => {
    const wrapper = mount(FieldSelect, { props: { value: '', def: { options: OPTIONS } } })
    const select = wrapper.findComponent(Select)
    expect(select.props('modelValue')).toBe('__empty__')
    select.vm.$emit('update:modelValue', 'large')
    select.vm.$emit('update:modelValue', '__empty__')
    expect(wrapper.emitted('update')).toEqual([['large'], ['']])
  })

  it('shows the first option for a value no option has, as a native select did, and writes nothing', () => {
    const wrapper = mount(FieldSelect, { props: { value: undefined, def: { options: { a: { value: 'a', label: 'A' }, b: { value: 'b', label: 'B' } } } } })
    expect(wrapper.findComponent(Select).props('modelValue')).toBe('a')
    expect(wrapper.emitted('update')).toBeUndefined()
  })
})

describe('FieldToggle', () => {
  it('reads anything truthy as on and emits the switch', () => {
    const wrapper = mount(FieldToggle, { props: { value: 1 } })
    const toggle = wrapper.findComponent(Switch)
    expect(toggle.props('modelValue')).toBe(true)
    toggle.vm.$emit('update:modelValue', false)
    expect(wrapper.emitted('update')).toEqual([[false]])
  })
})

describe('FieldNumber', () => {
  it('turns the min, max and step di.xml hands over as strings into numbers for the slider', () => {
    const wrapper = mount(FieldNumber, { props: { value: '6', def: { min: '1', max: '30', step: '1' } as never } })
    const slider = wrapper.findComponent(Slider)
    expect(slider.props('min')).toBe(1)
    expect(slider.props('max')).toBe(30)
    expect(slider.props('step')).toBe(1)
    expect(slider.props('modelValue')).toEqual([6])
  })

  it('starts from the minimum when there is no value', () => {
    const wrapper = mount(FieldNumber, { props: { value: '', def: { min: 4, max: 10 } } })
    expect(wrapper.findComponent(Slider).props('modelValue')).toEqual([4])
  })

  it('emits the slider position as a number', () => {
    const wrapper = mount(FieldNumber, { props: { value: 6, def: { min: 1, max: 30 } } })
    wrapper.findComponent(Slider).vm.$emit('update:modelValue', [12])
    expect(wrapper.emitted('update')).toEqual([[12]])
  })

  it('holds what is typed to the range on blur, and falls back to the minimum for nonsense', async () => {
    const wrapper = mount(FieldNumber, { props: { value: 6, def: { min: 1, max: 30 } } })
    const input = wrapper.find('input[type=number]')
    await input.setValue('99')
    await input.trigger('blur')
    expect(wrapper.emitted('update')!.at(-1)).toEqual([30])
    ;(input.element as HTMLInputElement).value = 'abc'
    await input.trigger('blur')
    expect(wrapper.emitted('update')!.at(-1)).toEqual([1])
  })
})

describe('FieldUrl', () => {
  it('takes a store path as it is', async () => {
    const wrapper = mount(FieldUrl, { props: { value: 'about-us' } })
    await wrapper.find('input').trigger('blur')
    expect(wrapper.text()).not.toContain('Invalid URL')
    expect(wrapper.find('a').exists()).toBe(false)
  })

  it('flags a broken absolute URL once the field is left', async () => {
    const wrapper = mount(FieldUrl, { props: { value: 'https://' } })
    expect(wrapper.text()).not.toContain('Invalid URL')
    await wrapper.find('input').trigger('blur')
    expect(wrapper.text()).toContain('Invalid URL')
    expect(wrapper.find('input').attributes('aria-invalid')).toBe('true')
  })

  it('offers to open a valid absolute URL', async () => {
    const wrapper = mount(FieldUrl, { props: { value: 'https://example.com/a' } })
    await flushPromises()
    expect(wrapper.find('a').attributes('href')).toBe('https://example.com/a')
  })
})
