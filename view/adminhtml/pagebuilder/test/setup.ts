import { afterEach, beforeEach } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'

// What happy-dom leaves out and the editor or Reka reach for

// FieldColor normalises colours through a 2D context; a CSS colour string passes through as the browser would
HTMLCanvasElement.prototype.getContext = function () {
  let fill = '#000000'
  return {
    get fillStyle() { return fill },
    set fillStyle(value: string) { fill = value },
  }
} as never

Element.prototype.scrollIntoView ??= () => {}
Element.prototype.hasPointerCapture ??= () => false
Element.prototype.setPointerCapture ??= () => {}
Element.prototype.releasePointerCapture ??= () => {}
globalThis.ResizeObserver ??= class {
  observe() {}
  unobserve() {}
  disconnect() {}
} as never

// Fresh stores per test; a component's store falls back to the active pinia when its app has none installed
beforeEach(() => setActivePinia(createPinia()))

// Portalled popovers and dialogs stay in <body> between tests otherwise
afterEach(() => {
  document.body.innerHTML = ''
})
