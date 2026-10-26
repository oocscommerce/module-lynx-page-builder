import { flushPromises, mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import SwitcherSelect from '@/components/SwitcherSelect.vue'

const OPTIONS = [
  { value: 'home', label: 'Home page' },
  { value: 'products', label: 'Products', children: [
    { value: 'simple', label: 'Products · Simple product page' },
    { value: 'bundle', label: 'Products · Bundle product page' },
  ] },
  { value: 'checkout', label: 'Checkout page' },
]

const items = () => [...document.body.querySelectorAll<HTMLElement>('[data-slot=command-item]')]
const item = (text: string) => items().find(i => i.textContent?.trim() === text)!
const filter = () => document.body.querySelector<HTMLInputElement>('[role=dialog] input, [data-reka-popper-content-wrapper] input')!

async function open(modelValue = 'home') {
  const wrapper = mount(SwitcherSelect, { props: { options: OPTIONS, modelValue, label: 'Page' }, attachTo: document.body })
  await wrapper.find('button').trigger('click')
  await flushPromises()
  return wrapper
}

describe('SwitcherSelect', () => {
  it('names the current choice on the button', () => {
    const wrapper = mount(SwitcherSelect, { props: { options: OPTIONS, modelValue: 'bundle', label: 'Page' } })
    expect(wrapper.text()).toContain('Products · Bundle product page')
  })

  it('opens inside the group that holds the current choice, without repeating the group in the rows', async () => {
    await open('bundle')
    expect(items().map(i => i.textContent?.trim())).toEqual(['Products', 'Simple product page', 'Bundle product page'])
  })

  it('drills into a group and back out of it', async () => {
    await open()
    item('Products').dispatchEvent(new PointerEvent('pointerup', { bubbles: true }))
    item('Products').click()
    await flushPromises()
    expect(items().map(i => i.textContent?.trim())).toContain('Simple product page')

    filter().dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowLeft', bubbles: true }))
    await flushPromises()
    expect(items().map(i => i.textContent?.trim())).toEqual(['Home page', 'Products', 'Checkout page'])
  })

  it('searches every level at once and picks from the results', async () => {
    const wrapper = await open()
    const input = filter()
    input.value = 'bundle'
    input.dispatchEvent(new Event('input', { bubbles: true }))
    await flushPromises()
    expect(items().map(i => i.textContent?.trim())).toEqual(['Products · Bundle product page'])

    item('Products · Bundle product page').click()
    await flushPromises()
    expect(wrapper.emitted('select')![0]![0]).toMatchObject({ value: 'bundle' })
  })

  it('says so when nothing matches', async () => {
    await open()
    const input = filter()
    input.value = 'zzz'
    input.dispatchEvent(new Event('input', { bubbles: true }))
    await flushPromises()
    expect(document.body.textContent).toContain('No match')
  })
})
