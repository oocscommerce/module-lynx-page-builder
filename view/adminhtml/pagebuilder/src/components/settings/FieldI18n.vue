<script setup lang="ts">
import { computed, inject, ref, type Component } from 'vue'
import { Languages, Plus, Trash2 } from '@lucide/vue'
import { Button } from '@/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/ui/dialog'
import { Input } from '@/ui/input'
import type { FieldDef } from './types'

type Localized = Record<string, string>

/**
 * A text_i18n / textarea_i18n / richtext_i18n setting: the text the admin writes (`default`) plus one entry per locale
 */
const props = defineProps<{ value?: string | Localized | null; def: FieldDef }>()
const emit = defineEmits<{ update: [value: Localized] }>()

const fieldComponents = inject<Record<string, Component>>('fieldComponents', {})
// 'text_i18n' → the 'text' field, and so on.
const base = computed(() => fieldComponents[String(props.def.type).replace(/_i18n$/, '')] ?? fieldComponents.text)

// A string is a layout written before the setting became localized
const current = computed<Localized>(() => (typeof props.value === 'object' && props.value !== null) ? props.value : { default: props.value ?? '' })
const translations = computed(() => Object.entries(current.value).filter(([k, v]) => k !== 'default' && v !== ''))

function updateDefault(text: string) {
  emit('update', { ...current.value, default: text })
}

// ── Dialog ────────────────────────────────────────────────────────────────────
const open = ref(false)
// Rows edited apart from the value, so Cancel leaves the setting untouched.
const rows = ref<{ locale: string; text: string }[]>([])

function openDialog() {
  rows.value = translations.value.map(([locale, text]) => ({ locale, text }))
  if (!rows.value.length) addRow()
  error.value = ''
  open.value = true
}

function addRow() {
  rows.value.push({ locale: '', text: '' })
}

const error = ref('')

function done() {
  const next: Localized = { default: current.value.default ?? '' }
  const seen = new Set<string>()
  error.value = ''
  for (const row of rows.value) {
    const locale = row.locale.trim()
    // A row left entirely blank is one the admin did not use.
    if (!locale && !row.text) continue
    if (!locale) { error.value = 'Every translation needs a locale code.'; return }
    if (locale === 'default') { error.value = '"default" is the text above, not a locale.'; return }
    if (!/^[a-z]{2,3}(_[A-Za-z]{2,8})*$/.test(locale)) { error.value = `"${locale}" is not a locale code (expected the form de_DE).`; return }
    if (seen.has(locale)) { error.value = `"${locale}" is listed twice.`; return }
    if (!row.text) { error.value = `The translation for ${locale} is empty.`; return }
    seen.add(locale)
    next[locale] = row.text
  }
  emit('update', next)
  open.value = false
}
</script>

<template>
  <div>
    <component :is="base" :value="current.default ?? ''" :def="def" @update="updateDefault" />
    <Button variant="ghost" size="xs" class="mt-1 text-muted-foreground" title="Translations" @click="openDialog">
      <Languages />
      Translations<span v-if="translations.length" class="font-semibold">({{ translations.length }})</span>
    </Button>

    <Dialog v-model:open="open">
      <DialogContent class="sm:max-w-xl max-h-[85vh] flex flex-col">
        <DialogHeader>
          <DialogTitle>Translations · {{ def.label }}</DialogTitle>
          <DialogDescription>
            One entry per locale code of a store view (Stores &gt; Configuration &gt; General &gt; Locale Options), for example <span class="font-mono">de_DE</span>, <span class="font-mono">fr_FR</span>, <span class="font-mono">es_ES</span>, <span class="font-mono">ja_JP</span>. A store whose code is not listed shows the default text.
          </DialogDescription>
        </DialogHeader>

        <div>
          <div class="text-xs font-semibold uppercase tracking-wide text-muted-foreground mb-1">Default</div>
          <div
            v-if="def.type === 'richtext_i18n'"
            class="text-sm pb-3 border-b break-words max-h-24 overflow-y-auto"
            v-html="current.default || '<em>empty</em>'"
          />
          <div v-else class="text-sm pb-3 border-b whitespace-pre-wrap break-words max-h-24 overflow-y-auto">{{ current.default || 'empty' }}</div>
        </div>

        <div class="flex-1 min-h-0 overflow-y-auto space-y-3 pr-1">
          <div v-for="(row, i) in rows" :key="i" class="flex items-start gap-2">
            <Input v-model="row.locale" type="text" placeholder="Locale" class="w-24 shrink-0 font-mono" />
            <div class="flex-1 min-w-0">
              <component :is="base" :value="row.text" :def="def" @update="row.text = $event" />
            </div>
            <Button variant="ghost" size="icon" class="shrink-0 text-muted-foreground hover:text-destructive" title="Remove" @click="rows.splice(i, 1)">
              <Trash2 />
            </Button>
          </div>
          <Button variant="outline" class="w-full border-dashed" @click="addRow">
            <Plus />
            Add translation
          </Button>
        </div>

        <p v-if="error" class="text-sm text-destructive">{{ error }}</p>
        <DialogFooter>
          <Button variant="outline" @click="open = false">Cancel</Button>
          <Button @click="done">Done</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </div>
</template>
