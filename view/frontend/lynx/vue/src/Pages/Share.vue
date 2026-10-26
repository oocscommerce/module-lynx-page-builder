<script setup lang="ts">
import { nextTick, ref } from 'vue'
import { useFormKeyStore } from 'OOCS_LynxTheme/stores/global/formKeyStore'

// Every string comes from Controller\Share\Open, where __() can reach it
const props = defineProps<{
    title: string
    intro: string
    label: string
    submit: string
    invalidMsg: string
    failed: boolean
    locked: boolean
    lockedMsg: string
    unlockUrl: string
    shareKey: string
}>()

const formKeyStore = useFormKeyStore()
const password = ref('')
// One red line under the field for every case: a locked link
const error = ref(props.locked ? props.lockedMsg : props.failed ? props.invalidMsg : '')

// Validated in JS, not by the browser: a password under 8 characters cannot be right, so the form does not post
function onSubmit(event: Event): void {
    if (password.value.length < 8) {
        event.preventDefault()
        error.value = props.invalidMsg
        // Back on the field once its message is drawn, so the message is read out
        const form = event.currentTarget as HTMLFormElement
        void nextTick(() => form.querySelector<HTMLElement>('[aria-invalid="true"]')?.focus())
        return
    }
    const field = (event.currentTarget as HTMLFormElement).elements.namedItem('form_key')
    if (field instanceof HTMLInputElement) {
        field.value = formKeyStore.ensure()
    }
}
</script>

<template>
    <div class="min-h-screen flex items-center justify-center px-4 py-16">
        <form
            method="post"
            :action="unlockUrl"
            novalidate
            class="l-card l-card-border border-base-300 bg-base-100 shadow-lg w-full max-w-sm p-8 grid gap-6"
            @submit="onSubmit"
        >
            <input type="hidden" name="form_key" :value="formKeyStore.value" />
            <input type="hidden" name="key" :value="shareKey" />

            <div class="grid gap-4 justify-items-center text-center">
                <div class="w-14 h-14 rounded-full bg-base-200 flex items-center justify-center text-base-content">
                    <svg class="w-6 h-6 fill-current" viewBox="0 0 24 24" aria-hidden="true">
                        <path
                            d="M12 2a5 5 0 0 0-5 5v3H6a2 2 0 0 0-2 2v7a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-7a2 2 0 0 0-2-2h-1V7a5 5 0 0 0-5-5zm3 8H9V7a3 3 0 1 1 6 0v3zm-3 4a1.5 1.5 0 0 1 1 2.6V19a1 1 0 1 1-2 0v-2.4a1.5 1.5 0 0 1 1-2.6z"
                        />
                    </svg>
                </div>
                <div class="grid gap-1.5">
                    <h1 class="text-xl font-semibold text-base-content">{{ title }}</h1>
                    <p class="text-sm text-base-content/70 leading-relaxed">{{ intro }}</p>
                </div>
            </div>

            <div class="grid gap-1.5">
                <label class="text-sm font-semibold text-base-content" for="share-password">{{ label }}</label>
                <input
                    id="share-password"
                    v-model="password"
                    type="password"
                    name="password"
                    class="l-input w-full"
                    autocomplete="new-password"
                    autofocus
                    :disabled="locked"
                    :aria-invalid="!!error"
                    :aria-describedby="error ? 'share-password-error' : undefined"
                    @input="error = ''"
                />
                <p v-if="error" id="share-password-error" class="text-sm text-error">{{ error }}</p>
            </div>

            <button type="submit" class="l-btn l-btn-primary w-full" :disabled="locked">{{ submit }}</button>
        </form>
    </div>
</template>
