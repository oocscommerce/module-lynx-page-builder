import { hydrate, mountNode } from '@oocs/module-lynx-theme/test'
import Share from 'OOCS_LynxPageBuilder/Pages/Share.vue'
import { describe, expect, it } from 'vitest'
import { nextTick } from 'vue'

// As Controller\Share\Open sends them
const props = {
    title: 'Design preview',
    intro: 'Enter the password to view this preview.',
    label: 'Password',
    submit: 'Open preview',
    invalidMsg: 'The password is not valid.',
    failed: false,
    locked: false,
    lockedMsg: 'Too many wrong passwords. This link is locked for a few minutes.',
    unlockUrl: 'https://shop.test/lynx/share/unlock/',
    shareKey: 'f3a9c2',
}

async function submit(form: HTMLFormElement) {
    const event = new Event('submit', { cancelable: true })
    form.dispatchEvent(event)
    await nextTick()
    return event
}

describe('Share', () => {
    it('shows the password form posting to the unlock URL', () => {
        const wrapper = mountNode(Share, { props })
        const form = wrapper.find('form')

        expect(wrapper.find('h1').text()).toBe('Design preview')
        expect(wrapper.text()).toContain('Enter the password to view this preview.')
        expect(wrapper.find('label[for="share-password"]').text()).toBe('Password')
        expect(form.attributes('action')).toBe(props.unlockUrl)
        expect(form.attributes('method')).toBe('post')
        expect(form.find('input[name="key"]').element.value).toBe('f3a9c2')
        expect(wrapper.find('button[type="submit"]').text()).toBe('Open preview')
        expect(wrapper.text()).not.toContain('The password is not valid.')
    })

    it('stops a password under 8 characters with the invalid message', async () => {
        const wrapper = mountNode(Share, { props })
        await wrapper.find('input[name="password"]').setValue('short')

        const event = await submit(wrapper.find('form').element)

        expect(event.defaultPrevented).toBe(true)
        expect(wrapper.text()).toContain('The password is not valid.')
    })

    it('posts a long enough password with a form key matching the cookie', async () => {
        const wrapper = mountNode(Share, { props })
        await wrapper.find('input[name="password"]').setValue('correct horse')

        const event = await submit(wrapper.find('form').element)

        const formKey = wrapper.find<HTMLInputElement>('input[name="form_key"]').element.value
        expect(event.defaultPrevented).toBe(false)
        expect(formKey).toMatch(/^[0-9a-zA-Z]{16}$/)
        expect(document.cookie).toContain(`form_key=${formKey}`)
    })

    it('shows the invalid message after a failed attempt until the visitor types', async () => {
        const wrapper = mountNode(Share, { props: { ...props, failed: true } })
        expect(wrapper.text()).toContain('The password is not valid.')

        await wrapper.find('input[name="password"]').setValue('a')

        expect(wrapper.text()).not.toContain('The password is not valid.')
    })

    it('locks the form while the link is locked', () => {
        const wrapper = mountNode(Share, { props: { ...props, locked: true, failed: true } })

        expect(wrapper.text()).toContain(props.lockedMsg)
        expect(wrapper.text()).not.toContain('The password is not valid.')
        expect(wrapper.find('input[name="password"]').attributes('disabled')).toBeDefined()
        expect(wrapper.find('button[type="submit"]').attributes('disabled')).toBeDefined()
    })

    it('server-renders and hydrates the same markup', async () => {
        const { html, warnings } = await hydrate(Share, { props })

        expect(html).toContain('Design preview')
        expect(warnings).toEqual([])
    })
})
