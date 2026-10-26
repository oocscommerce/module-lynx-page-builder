import { describe, expect, it, vi } from 'vitest'
import { usePageBuilder } from '@/api'
import { usePageBuilderStore } from '@/stores/pageBuilder'
import { usePreviewStore } from '@/stores/preview'

describe('usePageBuilder', () => {
  it('reads the layout in its stored shape, refuses writes, and acts through the store', () => {
    usePageBuilderStore().init({ layout: { content: [{ component: 'T/Components/Heading', name: 'heading', props: {} }] } })
    const pb = usePageBuilder()
    expect(pb.layout.content![0]).toMatchObject({ component: 'T/Components/Heading', name: 'heading' })

    const warn = vi.spyOn(console, 'warn').mockImplementation(() => {})
    ;(pb.layout.content![0] as { name: string }).name = 'changed'
    expect(usePageBuilderStore().layout.content![0]!.name).toBe('heading')
    warn.mockRestore()

    pb.openTab('seo')
    expect(pb.activeTab).toBe('seo')
    pb.selectNode(String(pb.layout.content![0]!.id))
    expect(pb.selectedNodeId).toBe(pb.layout.content![0]!.id)
    expect(pb.frameAway).toBe(usePreviewStore().frameAway)
  })
})
