import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import fieldComponents from '@/components/settings/index'
import FieldArray from '@/components/settings/FieldArray.vue'
import FieldColor from '@/components/settings/FieldColor.vue'
import FieldI18n from '@/components/settings/FieldI18n.vue'
import FieldImage from '@/components/settings/FieldImage.vue'
import FieldRichText from '@/components/settings/FieldRichText.vue'
import { usePageBuilderStore } from '@/stores/pageBuilder'

const global = { provide: { fieldComponents } }
const inBody = (selector: string) => [...document.body.querySelectorAll<HTMLElement>(selector)]
const buttonInBody = (text: string) => inBody('button').find(b => b.textContent?.trim() === text)!

const ITEM_FIELDS = {
  title: { type: 'text', label: 'Title', default: 'New' },
  shown: { type: 'toggle', label: 'Shown' },
  tags: { type: 'array', label: 'Tags' },
  size: { type: 'number', label: 'Size', min: 2 },
}

afterEach(() => vi.unstubAllGlobals())

describe('FieldArray', () => {
  const mountArray = (value: Record<string, unknown>[]) =>
    mount(FieldArray, { props: { value, def: { fields: ITEM_FIELDS, labelField: 'title' } }, global, attachTo: document.body })

  it('adds an item holding each field’s starting value, unfolded', async () => {
    const wrapper = mountArray([])
    expect(wrapper.text()).toContain('No items yet')
    await wrapper.findAll('button').find(b => b.text() === 'Add item')!.trigger('click')
    expect(wrapper.emitted('update')!.at(-1)).toEqual([[{ title: 'New', shown: false, tags: [], size: 2 }]])
    await wrapper.setProps({ value: wrapper.emitted('update')!.at(-1)![0] as never })
    expect(wrapper.text()).toContain('Title')
  })

  it('names a row by its label field, the default text of a localized one, else its position', () => {
    const wrapper = mountArray([{ title: 'First' }, { title: { default: 'Second', de_DE: 'Zweite' } }, {}])
    expect(wrapper.text()).toContain('First')
    expect(wrapper.text()).toContain('Second')
    expect(wrapper.text()).toContain('Item 3')
  })

  it('asks before removing, and keeps the unfolded rows unfolded', async () => {
    const wrapper = mountArray([{ title: 'A' }, { title: 'B' }, { title: 'C' }])
    const expand = () => wrapper.findAll('button[title=Expand]')
    await expand()[2]!.trigger('click')
    await wrapper.findAll('button[title=Delete]')[0]!.trigger('click')
    expect(wrapper.text()).toContain('Delete this item?')
    await wrapper.findAll('button').find(b => b.text() === 'Yes')!.trigger('click')
    const next = wrapper.emitted('update')!.at(-1)![0] as Record<string, unknown>[]
    expect(next.map(item => item.title)).toEqual(['B', 'C'])
    await wrapper.setProps({ value: next as never })
    // C was unfolded at 2 and is unfolded at 1 now
    expect(wrapper.findAll('label').length).toBeGreaterThan(0)
    expect(wrapper.findAll('input[type=text]').map(i => (i.element as HTMLInputElement).value)).toContain('C')
  })

  it('moves an item to where it is dropped', async () => {
    const wrapper = mountArray([{ title: 'A' }, { title: 'B' }, { title: 'C' }])
    const dataTransfer = { setData: () => {}, effectAllowed: '' }
    await wrapper.findAll('[draggable=true]')[0]!.trigger('dragstart', { dataTransfer })
    const rows = wrapper.findAll('.border.rounded-md')
    await rows[2]!.trigger('dragover')
    await rows[2]!.trigger('drop')
    expect((wrapper.emitted('update')!.at(-1)![0] as Record<string, unknown>[]).map(item => item.title)).toEqual(['B', 'C', 'A'])
  })

  it('updates one field of one item', async () => {
    const wrapper = mountArray([{ title: 'A' }, { title: 'B' }])
    await wrapper.findAll('button[title=Expand]')[1]!.trigger('click')
    await wrapper.find('input[type=text]').setValue('Bee')
    expect(wrapper.emitted('update')!.at(-1)).toEqual([[{ title: 'A' }, { title: 'Bee' }]])
  })
})

describe('FieldI18n', () => {
  const def = { type: 'text_i18n', label: 'Message' }

  it('edits the default text, keeping the translations', async () => {
    const wrapper = mount(FieldI18n, { props: { value: { default: 'Hi', de_DE: 'Hallo' }, def }, global })
    await wrapper.find('input').setValue('Hello')
    expect(wrapper.emitted('update')!.at(-1)).toEqual([{ default: 'Hello', de_DE: 'Hallo' }])
    expect(wrapper.text()).toContain('(1)')
  })

  it('reads a plain string from before the setting was localized as its default', () => {
    const wrapper = mount(FieldI18n, { props: { value: 'Old', def }, global })
    expect((wrapper.find('input').element as HTMLInputElement).value).toBe('Old')
  })

  it('checks each locale code before keeping the translations', async () => {
    const wrapper = mount(FieldI18n, { props: { value: { default: 'Hi' }, def }, global, attachTo: document.body })
    await wrapper.find('button[title=Translations]').trigger('click')
    await flushPromises()
    const [locale, text] = inBody('[role=dialog] input') as HTMLInputElement[]
    const type = async (input: HTMLInputElement, value: string) => { input.value = value; input.dispatchEvent(new Event('input')); await flushPromises() }

    await type(locale!, 'german')
    await type(text!, 'Hallo')
    buttonInBody('Done').click()
    await flushPromises()
    expect(document.body.textContent).toContain('"german" is not a locale code')
    expect(wrapper.emitted('update')).toBeUndefined()

    await type(locale!, 'de_DE')
    buttonInBody('Done').click()
    await flushPromises()
    expect(wrapper.emitted('update')!.at(-1)).toEqual([{ default: 'Hi', de_DE: 'Hallo' }])
  })
})

describe('FieldColor', () => {
  it('stores a solid colour picked from the swatches as #rrggbb and closes', async () => {
    const wrapper = mount(FieldColor, {
      props: { value: '' },
      attachTo: document.body,
    })
    await wrapper.find('button').trigger('click')
    await flushPromises()
    const swatch = inBody('button[title="rgb(41 84 255)"]')[0]!
    swatch.click()
    await flushPromises()
    expect(wrapper.emitted('update')!.at(-1)).toEqual(['#2954ff'])
    expect(inBody('button[title="rgb(41 84 255)"]')).toHaveLength(0)
  })

  it('takes a value typed in, trimmed', async () => {
    const wrapper = mount(FieldColor, { props: { value: '' } })
    await wrapper.find('input').setValue('  #fff ')
    expect(wrapper.emitted('update')!.at(-1)).toEqual(['#fff'])
  })
})

describe('FieldRichText', () => {
  it('does not echo a value set from outside back as an update', async () => {
    const wrapper = mount(FieldRichText, { props: { value: '<p>One</p>' } })
    await flushPromises()
    await wrapper.setProps({ value: '<p>Two</p>' })
    await flushPromises()
    expect(wrapper.html()).toContain('Two')
    expect(wrapper.emitted('update')).toBeUndefined()
  })
})

describe('FieldImage', () => {
  const contents = {
    success: true,
    pathId: 'wysiwyg',
    parent: '',
    dirs: [{ id: 'wysiwyg/sale', name: 'sale' }],
    files: [{ path: 'wysiwyg/banner.jpg', name: 'banner.jpg', thumb: '/thumb/banner.jpg' }],
  }

  it('opens the media browser where the picture lives and stores the path picked', async () => {
    usePageBuilderStore().init({ mediaContentsUrl: '/contents', mediaBaseUrl: '/media' })
    const fetch = vi.fn().mockResolvedValue({ json: async () => contents })
    vi.stubGlobal('fetch', fetch)
    const wrapper = mount(FieldImage, { props: { value: 'wysiwyg/old.jpg' }, attachTo: document.body })
    expect(wrapper.find('img').attributes('src')).toBe('/media/wysiwyg/old.jpg')

    await wrapper.findAll('button').find(b => b.text() === 'Replace')!.trigger('click')
    await flushPromises()
    expect((fetch.mock.calls[0]![1].body as FormData).get('path')).toBe('wysiwyg')
    expect(document.body.textContent).toContain('1 image · 1 folder')

    inBody('button[title="banner.jpg"]')[0]!.click()
    await flushPromises()
    expect(wrapper.emitted('update')!.at(-1)).toEqual(['wysiwyg/banner.jpg'])
  })

  it('asks before emptying the field', async () => {
    const wrapper = mount(FieldImage, { props: { value: 'a/b.png' } })
    await wrapper.findAll('button').find(b => b.text() === 'Remove')!.trigger('click')
    expect(wrapper.text()).toContain('Remove this image?')
    await wrapper.findAll('button').find(b => b.text() === 'Yes')!.trigger('click')
    expect(wrapper.emitted('update')!.at(-1)).toEqual([''])
  })
})
