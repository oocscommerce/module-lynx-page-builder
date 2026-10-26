<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { Info } from '@lucide/vue'
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from '@/ui/accordion'
import { Alert, AlertDescription } from '@/ui/alert'
import { Button } from '@/ui/button'
import { Label } from '@/ui/label'
import { Textarea } from '@/ui/textarea'
import fieldComponents from './settings/index'
import TooltipIcon from './TooltipIcon.vue'
import type { FieldDef } from './settings/types'

type ThemeField = FieldDef & { group?: string; section?: string; description?: string; var?: string; html?: string; values?: Record<string, string> }

/**
 * The Theme tab: the design's settings (Model\ThemeSettings
 */
const props = withDefaults(defineProps<{
  // key => { group, label, description, type, default, options?, min?, max?, step? }
  fields?: Record<string, ThemeField>
  // The design's values, a full set; mutated in place so the editor sees changes.
  values: Record<string, any>
  // What the design has saved (as loaded, then as of the last Save): what Reset returns to.
  saved?: Record<string, any>
  canSave?: boolean
}>(), { fields: () => ({}), saved: () => ({}), canSave: true })

// Fields by group, groups in the order they first appear in the pool
const groups = computed(() => {
  const out: { name: string; fields: { key: string; def: ThemeField; section: string }[] }[] = []
  for (const [key, def] of Object.entries(props.fields)) {
    const name = def.group ?? 'General'
    let group = out.find(g => g.name === name)
    if (!group) { group = { name, fields: [] }; out.push(group) }
    const previous = group.fields[group.fields.length - 1]
    const section = def.section && def.section !== previous?.def?.section ? def.section : ''
    group.fields.push({ key, def, section })
  }
  return out
})

// An accordion: every group is a row, a click unfolds its fields underneath
const open = ref(new Set<string>())

// One accordion for the import row and the groups; the import row is its own flag
const IMPORT = '__import__'
const openRows = computed(() => [...(importOpen.value ? [IMPORT] : []), ...open.value])
function setOpenRows(rows: string[] | undefined) {
  importOpen.value = !!rows?.includes(IMPORT)
  open.value = new Set((rows ?? []).filter(row => row !== IMPORT))
}

// The draft being edited; follows `values` when they change from outside.
const draft = reactive<Record<string, any>>({ ...props.values })
watch(() => ({ ...props.values }), v => Object.assign(draft, v))

const hasChanges = computed(() =>
  Object.keys(props.fields).some(key => String(draft[key] ?? '') !== String(props.values[key] ?? ''))
)
const isSaved = computed(() =>
  Object.keys(props.fields).every(key => String(draft[key] ?? '') === String(props.saved[key] ?? ''))
)

// ── Import a generated theme ──────────────────────────────────────────────────
const importOpen = ref(false)
const importText = ref('')
const importResult = ref('')

function coerce(def, value) {
  if (def.type === 'color' || def.type === 'text') return value
  if (def.type === 'number') {
    // A theme writes lengths in rem; a px setting has to convert rather than read the number off the front of "2rem"
    const px = toPx(value)
    const n = px === null ? Math.round(parseFloat(value)) : Math.round(px)
    if (Number.isNaN(n)) return null
    const lo = def.min ?? n
    const hi = def.max ?? n
    return String(Math.min(Math.max(n, lo), hi))
  }
  if (def.type === 'select') {
    for (const [key, css] of Object.entries(def.values ?? {})) {
      if (String(css) === value || sameLength(String(css), value)) return key
    }
    // A select may use its option name as the value itself (border width "1px", the corner scale "0.5rem")
    for (const key of Object.keys(def.options ?? {})) {
      if (key === value || sameLength(key, value)) return key
    }
    return null
  }
  return null
}

// Lengths compare by value, not by spelling: a generated theme says `0rem` where a setting may say `0px`
function toPx(v) {
  const m = /^(-?[\d.]+)(px|rem|em)?$/.exec(String(v).trim())
  return m ? parseFloat(m[1]) * (m[2] === 'rem' || m[2] === 'em' ? 16 : 1) : null
}

function sameLength(a, b) {
  const x = toPx(a)
  const y = toPx(b)
  return x !== null && y !== null && x === y
}

// Copies a variable the paste carried into a setting it has no name for.
function follow(sourceVar, targetKey, seen, taken) {
  const def = props.fields[targetKey]
  if (!def?.var || seen[def.var] !== undefined || seen[sourceVar] === undefined) return
  const value = coerce(def, seen[sourceVar])
  if (value === null) return
  draft[targetKey] = value
  taken.push(def.var)
}

function importTheme() {
  const text = importText.value.replace(/\/\*[\s\S]*?\*\//g, '')
  const body = text.includes('{')
    ? text.slice(text.indexOf('{') + 1, text.lastIndexOf('}'))
    : text

  const byName: Record<string, [string, ThemeField]> = {}
  for (const [key, def] of Object.entries(props.fields)) {
    if (def.var) byName[def.var] = [key, def]
    if (def.html) byName[def.html] = [key, def]
  }

  const taken: string[] = []
  const spilled: string[] = []
  const skipped: string[] = []
  const seen: Record<string, string> = {}
  for (const decl of body.split(';')) {
    const at = decl.indexOf(':')
    if (at === -1) continue
    const name = decl.slice(0, at).trim()
    const value = decl.slice(at + 1).trim().replace(/^["']|["']$/g, '')
    if (!value) continue
    // `name`, `default` and `prefersdark` are plugin directives, not variables.
    if (!name.startsWith('--') && name !== 'color-scheme') continue
    seen[name] = value
    const hit = byName[name]
    if (!hit) {
      // No setting owns it: Custom CSS is the only place left
      spilled.push(`${name}:${value}`)
      continue
    }
    const coerced = coerce(hit[1], value)
    if (coerced === null) {
      // A setting owns it but cannot express this value
      skipped.push(`${name}: ${value}`)
      continue
    }
    draft[hit[0]] = coerced
    taken.push(name)
  }

  // Two settings have no counterpart in a generated theme, so a pasted one never carries them
  follow('--radius-field', 'radius_button', seen, taken)
  follow('--color-primary', 'color_link', seen, taken)

  // One imported block at a time
  const kept = (draft.custom_css || '').replace(/\/\* imported \*\/\s*\n?:root\{[^}]*\}\n?/g, '').trim()
  const block = spilled.length ? `/* imported */\n:root{${spilled.join(';')}}` : ''
  draft.custom_css = block ? (kept ? `${kept}\n${block}` : block) : kept
  importResult.value = taken.length || spilled.length || skipped.length
    ? `${taken.length} setting updated`
      + (spilled.length ? ` · ${spilled.length} added to Custom CSS: ${spilled.map(d => d.split(':')[0]).join(', ')}` : '')
      + (skipped.length ? ` · ${skipped.length} not imported, no matching option: ${skipped.join(', ')}` : '')
    : 'Nothing recognised — paste a daisyUI theme block'
}

function update(key, value) {
  draft[key] = value
}

function reset() {
  for (const key of Object.keys(props.fields)) draft[key] = props.saved[key] ?? ''
}

function apply() {
  Object.assign(props.values, draft)
}

function discard() {
  Object.assign(draft, props.values)
}
</script>

<template>
  <div class="flex flex-col h-full">
    <div class="flex items-center justify-between px-4 pt-3 pb-2 border-b shrink-0">
      <span class="text-sm font-semibold">Theme settings</span>
      <Button
        variant="ghost"
        size="sm"
        :disabled="isSaved"
        title="Put every setting back to what the design has saved (then Apply)"
        @click="reset"
      >Reset</Button>
    </div>

    <!-- A read-only design still previews every change; only Save is off, like the layout. -->
    <Alert v-if="!canSave" class="mx-4 my-3 w-auto shrink-0">
      <Info />
      <AlertDescription>This design is read-only: changes show in the preview but cannot be saved. Duplicate it to keep them.</AlertDescription>
    </Alert>

    <!-- Accordion: one row per group, its fields unfold underneath -->
    <div class="flex-1 overflow-y-auto pb-scroll min-h-0">
      <Accordion type="multiple" :model-value="openRows" @update:model-value="setOpenRows($event as string[] | undefined)">
        <!-- Import: reads a pasted theme block into the fields below -->
        <AccordionItem :value="IMPORT">
          <AccordionTrigger class="px-4 hover:no-underline hover:bg-accent rounded-none">Import daisyUI theme</AccordionTrigger>
          <AccordionContent class="px-4 pt-2">
            <p class="text-sm text-muted-foreground leading-relaxed mb-2">
              Get the CSS from the
              <a href="https://daisyui.com/theme-generator/" target="_blank" rel="noopener" class="underline underline-offset-2 hover:text-foreground">daisyUI theme generator</a>
              and paste it here.
            </p>
            <Textarea
              v-model="importText"
              rows="8"
              spellcheck="false"
              placeholder='@plugin "daisyui/theme" {&#10;  --color-primary: oklch(58% 0.233 277.117);&#10;  --radius-box: 0.5rem;&#10;}'
              class="font-mono text-xs leading-relaxed resize-y"
            />
            <div class="flex items-center gap-2 mt-2">
              <Button size="sm" :disabled="!importText.trim()" @click="importTheme">Import</Button>
              <span v-if="importResult" class="text-sm text-muted-foreground leading-snug">{{ importResult }}</span>
            </div>
          </AccordionContent>
        </AccordionItem>

        <AccordionItem v-for="group in groups" :key="group.name" :value="group.name">
          <AccordionTrigger class="px-4 hover:no-underline hover:bg-accent rounded-none">{{ group.name }}</AccordionTrigger>
          <AccordionContent class="px-4 pt-2">
            <div v-for="{ key, def, section } in group.fields" :key="key" class="mb-4 last:mb-0">
              <div v-if="section" class="text-xs font-semibold uppercase tracking-wide text-muted-foreground mt-5 first:mt-0 mb-2">{{ section }}</div>
              <div class="flex items-center gap-1 mb-2">
                <Label>{{ def.label }}</Label>
                <TooltipIcon v-if="def.description" :text="def.description" />
              </div>
              <component
                :is="fieldComponents[def.type ?? ''] ?? fieldComponents.text"
                :value="draft[key] ?? ''"
                :def="def"
                @update="update(key, $event)"
              />
            </div>
          </AccordionContent>
        </AccordionItem>
      </Accordion>
    </div>

    <!-- Apply / Discard -->
    <div
      class="flex items-center justify-end gap-2 px-4 py-2.5 border-t bg-muted/50 shrink-0"
      :class="hasChanges ? '' : 'invisible'"
    >
      <Button variant="ghost" @click="discard">Discard</Button>
      <Button title="Render the preview with these settings; Save writes them to the design" @click="apply">Apply</Button>
    </div>
  </div>
</template>
