import { defineStore } from 'pinia'
import { ref } from 'vue'

/**
 * The preview frame: how it is shown, where it is, and the line to the bridge inside it
 */
export const usePreviewStore = defineStore('preview', () => {
  const device = ref('desktop')
  // Show all outlines
  const outlines = ref(false)
  // The frame followed a link off the page being edited: the outline is read-only until it is back
  const frameAway = ref(false)

  // Set by PreviewArea while mounted: the store holds no frame, only the way to post to it
  let sender: ((message: Record<string, unknown>) => void) | null = null
  function connect(send: typeof sender) {
    sender = send
  }
  function send(message: Record<string, unknown>) {
    sender?.(message)
  }

  /** Frames a node in the preview, or none; a press in the page hides the frame until this comes again. */
  function highlight(id: string | null) {
    send({ type: 'lynx:highlight', id })
  }

  return { device, outlines, frameAway, connect, send, highlight }
})
