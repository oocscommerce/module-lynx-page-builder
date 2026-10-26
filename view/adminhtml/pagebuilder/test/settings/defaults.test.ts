import { describe, expect, it } from 'vitest'
import { settingsDefaults } from '@/components/settings/defaults'

describe('settingsDefaults', () => {
  it('takes every declared default and skips the settings without one', () => {
    expect(settingsDefaults({
      title: { type: 'text', default: 'Hello' },
      count: { type: 'number', default: 3 },
      color: { type: 'color' },
    })).toEqual({ title: 'Hello', count: 3 })
  })

  it('gives a localized setting its object form', () => {
    expect(settingsDefaults({ text: { type: 'text_i18n', default: 'Hi' } })).toEqual({ text: { default: 'Hi' } })
  })

  it('copies a default so a node never shares it with the definition', () => {
    const def = { items: { type: 'array', default: [{ label: 'A' }] } }
    const out = settingsDefaults(def) as { items: { label: string }[] }
    out.items[0]!.label = 'B'
    expect(def.items.default[0]!.label).toBe('A')
  })

  it('answers nothing for no definitions', () => {
    expect(settingsDefaults(undefined)).toEqual({})
  })
})
