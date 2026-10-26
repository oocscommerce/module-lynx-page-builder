import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import ShareDialog from '@/components/ShareDialog.vue'

const SHARE = { listUrl: '/list', createUrl: '/create', revokeUrl: '/revoke', hours: [4, 24, 72] }
const button = (text: string) => [...document.body.querySelectorAll('button')].find(b => b.textContent?.trim() === text)!
const passwordInput = () => document.body.querySelector<HTMLInputElement>('#pb-share-password')!

function stubFetch(created: Record<string, unknown> = { success: true, id: 9, url: 'https://shop.test/s/abc', password: 'p', expires_label: 'tomorrow' }) {
  const fetch = vi.fn(async (url: string) => ({
    ok: true,
    json: async () => (url === '/list' ? { shares: [{ id: 3, created_at: '2026-10-15T00:00:00Z', expires_at: '2026-10-18T00:00:00Z', expires_label: 'Oct 18' }] } : created),
  }))
  vi.stubGlobal('fetch', fetch)
  return fetch
}

afterEach(() => vi.unstubAllGlobals())

describe('ShareDialog', () => {
  it('opens clean, a day by default, and lists the links that still work', async () => {
    stubFetch()
    mount(ShareDialog, { props: { share: SHARE, open: true }, attachTo: document.body })
    await flushPromises()
    expect(passwordInput().value).toBe('')
    expect(document.body.textContent).toContain('1 day')
    expect(document.body.textContent).toContain('3 days')
    expect(document.body.textContent).toContain('until Oct 18')
    expect(button('Create link').disabled).toBe(true)
  })

  it('generates a ten-character password without look-alike characters, shown', async () => {
    stubFetch()
    mount(ShareDialog, { props: { share: SHARE, open: true }, attachTo: document.body })
    await flushPromises()
    button('Generate').click()
    await flushPromises()
    expect(passwordInput().value).toMatch(/^[a-km-np-zA-HJ-NP-Z2-9]{10}$/)
    expect(passwordInput().type).toBe('text')
  })

  it('creates a link with the password and the hours, then shows it', async () => {
    const fetch = stubFetch()
    mount(ShareDialog, { props: { share: SHARE, open: true }, attachTo: document.body })
    await flushPromises()
    const input = passwordInput()
    input.value = 'secret-123'
    input.dispatchEvent(new Event('input'))
    await flushPromises()
    button('Create link').click()
    await flushPromises()
    const body = fetch.mock.calls.find(([url]) => url === '/create')![1]!.body as FormData
    expect(body.get('password')).toBe('secret-123')
    expect(body.get('hours')).toBe('24')
    expect(document.body.querySelector<HTMLInputElement>('input[readonly]')!.value).toBe('https://shop.test/s/abc')
  })

  it('passes a refusal on as a notice', async () => {
    stubFetch({ success: false, message: 'Too short' })
    const wrapper = mount(ShareDialog, { props: { share: SHARE, open: true }, attachTo: document.body })
    await flushPromises()
    const input = passwordInput()
    input.value = 'secret-123'
    input.dispatchEvent(new Event('input'))
    await flushPromises()
    button('Create link').click()
    await flushPromises()
    expect(wrapper.emitted('notify')).toEqual([['error', 'Too short']])
  })
})
