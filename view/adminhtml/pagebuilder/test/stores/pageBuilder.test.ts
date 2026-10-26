import { describe, expect, it, vi } from 'vitest'
import { nextTick } from 'vue'
import { usePageBuilderStore } from '@/stores/pageBuilder'
import { usePreviewStore } from '@/stores/preview'

const HEADING = { component: 'T/Components/Heading', name: 'heading', label: 'Heading', props: { text: 'Hi' } }
const THEME_FIELDS = {
  radius: { type: 'number', min: 0, max: 2 },
  font: { type: 'select', options: { a: { value: 'sans' }, b: { value: 'serif' } } },
  primary: { type: 'color' },
}

function store(config = {}) {
  const pb = usePageBuilderStore()
  pb.init({ layout: { header: [HEADING], content: [HEADING] }, ...config })
  return pb
}

describe('page builder store', () => {
  it('starts history at the loaded layout, then records each change', async () => {
    const pb = store()
    await nextTick()
    expect(pb.canUndo).toBe(false)
    pb.layout.content![0]!.visible = false
    await nextTick()
    expect(pb.canUndo).toBe(true)
    pb.undo()
    expect(pb.layout.content![0]!.visible).toBe(true)
  })

  it('writes only the editable zones of a layout applied', () => {
    const pb = store({ editableZones: ['content'] })
    pb.applyLayout({ header: [], content: [] })
    expect(pb.layout.header).toHaveLength(1)
    expect(pb.layout.content).toHaveLength(0)
  })

  it('holds theme values to their fields', () => {
    const pb = store({ themeSettings: { fields: THEME_FIELDS, values: { primary: 'red' } } })
    expect(pb.normalizeTheme({ radius: '5', font: 'mono', other: 'x', primary: ' blue ' })).toEqual({ radius: '2', primary: 'blue' })
    pb.applyTheme({ font: 'serif' })
    expect(pb.theme.font).toBe('serif')
    expect(pb.themeColors).toEqual(['red'])
  })

  it('writes no theme when the theme is not this editor’s', () => {
    const locked = store({ themeSettings: { fields: THEME_FIELDS, values: {} }, canEditTheme: false })
    locked.applyTheme({ font: 'serif' })
    expect(locked.theme.font).toBeUndefined()
  })

  it('opens the picker beside a node picked in the preview, unless the frame is on another page', () => {
    const pb = store()
    const id = pb.layout.content![0]!.id
    pb.insertFromPreview({ id, position: 'after' })
    expect(pb.pickerTarget).toEqual({ id: 'content', slot: null, index: 1 })

    pb.closePicker()
    usePreviewStore().frameAway = true
    pb.insertFromPreview({ id, position: 'before' })
    expect(pb.pickerTarget).toBeNull()
    expect(pb.canUndo).toBe(false)
  })

  it('frames the node in the preview again when it is picked a second time', () => {
    const pb = store()
    const send = vi.fn()
    usePreviewStore().connect(send)
    const node = pb.layout.content![0]!
    pb.selectFromPreview(node.id)
    expect(send).not.toHaveBeenCalled()
    pb.selectFromPreview(node.id)
    expect(send).toHaveBeenCalledWith({ type: 'lynx:highlight', id: node.id })
  })
})
