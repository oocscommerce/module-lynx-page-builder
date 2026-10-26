<script setup lang="ts">
import { computed, nextTick, provide, ref, watch } from 'vue'
import { Pencil, X } from '@lucide/vue'
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from '@/ui/accordion'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
import { Label } from '@/ui/label'
import { usePageBuilderStore } from '@/stores/pageBuilder'
import fieldComponents from './settings/index'
import TooltipIcon from './TooltipIcon.vue'
import { settingsDefaults } from './settings/defaults'
import type { FieldDef } from './settings/types'
import type { ComponentDef, LayoutNode } from './types'

const props = defineProps<{ node: LayoutNode }>()
const emit = defineEmits<{ close: [] }>()

const { componentOf } = usePageBuilderStore()
provide('fieldComponents', fieldComponents)
const settingsDef = computed<Record<string, FieldDef>>(() => componentOf(props.node)?.settings ?? {})
// The key as registered, module::path, in pieces that may wrap only at :: and /.
const componentId = computed(() => {
  const [module, ...path] = props.node.key.split('/')
  return [module + '::', ...path.map((p, i) => (i ? '/' : '') + p)]
})

// Node settings over the component defaults, so a node saved before a setting existed gets the default
const withDefaults = () => ({ ...settingsDefaults(settingsDef.value), ...JSON.parse(JSON.stringify(props.node.settings ?? {})) })

const localSettings = ref<Record<string, any>>(withDefaults())
const saveError     = ref('')
const confirmClose  = ref(false)

// Like system.xml <depends>: every listed setting must hold one of the given values (an array lists alternatives)
function visible(def) {
  return Object.entries(def.depends ?? {}).every(([key, expected]) =>
    (expected && typeof expected === 'object' ? Object.values(expected) : [expected])
      .some(value => String(value) === String(localSettings.value[key])))
}

// Only the settings on screen: a hidden one is dropped on save and falls back to its default
const visibleSettings = (settings) => Object.fromEntries(
  Object.entries(settings).filter(([key]) => !settingsDef.value[key] || visible(settingsDef.value[key])),
)

// Groups only when a setting names one (`tab`); the rest go to General, first. Shown as the Theme tab's accordion.
const GENERAL = 'General'
const tabOf = (def) => def.tab || GENERAL
const tabs = computed(() => {
  const defs = Object.values(settingsDef.value)
  if (!defs.some(def => def.tab)) return []
  const names = [...new Set(defs.map(tabOf))]
  return names.includes(GENERAL) ? [GENERAL, ...names.filter(name => name !== GENERAL)] : names
})
// A tab whose every setting `depends` hides is left out
const shownTabs = computed(() =>
  tabs.value.filter(name => Object.values(settingsDef.value).some(def => tabOf(def) === name && visible(def))),
)
// The first group starts unfolded
const firstOpen = () => new Set(shownTabs.value.slice(0, 1))
const open = ref<Set<string>>(firstOpen())

const hasChanges = computed(() =>
  JSON.stringify(visibleSettings(localSettings.value)) !== JSON.stringify(visibleSettings(withDefaults()))
)

watch(() => props.node.id, () => {
  localSettings.value = withDefaults()
  saveError.value     = ''
  confirmClose.value  = false
  editingName.value   = false
  open.value          = firstOpen()
})

function updateField(key, value) {
  localSettings.value[key] = value
  saveError.value = ''
}

// The same rule as FieldUrl.vue: a store path (`about-us`, `#top`) is always fine
const validators: Record<string, (v: string) => boolean> = {
  url: (v) => !v || !/^[a-z][a-z0-9+.-]*:/i.test(v) || (() => { try { new URL(v); return true } catch { return false } })(),
}

function save() {
  for (const [key, def] of Object.entries(settingsDef.value)) {
    if (!visible(def)) continue
    const validate = validators[def.type ?? '']
    if (validate && !validate(localSettings.value[key] ?? '')) {
      // Unfolded where it can be fixed
      open.value = new Set([...open.value, tabOf(def)])
      saveError.value = `"${def.label}" contains an invalid value.`
      return
    }
  }
  if (!props.node.settings) props.node.settings = {}
  Object.assign(props.node.settings, visibleSettings(localSettings.value))
  for (const [key, def] of Object.entries(settingsDef.value)) {
    if (!visible(def)) delete props.node.settings[key]
  }
  saveError.value = ''
}

function tryClose() {
  if (hasChanges.value) {
    confirmClose.value = true
  } else {
    emit('close')
  }
}

// ── Inline name editing ───────────────────────────────────────────────────────

const editingName    = ref(false)
const nameInputValue = ref('')
const nameInputEl    = ref<InstanceType<typeof Input> | null>(null)

function startEditName() {
  if (confirmClose.value) return
  nameInputValue.value = props.node.custom_name ?? ''
  editingName.value    = true
  nextTick(() => (nameInputEl.value?.$el as HTMLInputElement | undefined)?.select())
}

function commitName() {
  const trimmed = nameInputValue.value.trim()
  props.node.custom_name = trimmed || undefined
  editingName.value = false
}

function cancelEditName() {
  editingName.value = false
}
</script>

<template>
  <div class="h-full flex flex-col">

    <!-- Header -->
    <div class="flex items-center gap-2 px-4 py-2.5 shrink-0 bg-background border-b">

      <!-- Name area -->
      <div class="flex-1 min-w-0">
        <div class="text-xs font-medium text-muted-foreground mb-0.5">Settings</div>

        <!-- Editable name -->
        <Input
          v-if="editingName"
          ref="nameInputEl"
          v-model="nameInputValue"
          type="text"
          :placeholder="node.label"
          class="h-7 px-2 font-semibold"
          @blur="commitName"
          @keydown.enter.prevent="commitName"
          @keydown.escape.prevent="cancelEditName"
        />

        <!-- Display name (click to edit) -->
        <div
          v-else
          class="group flex items-center gap-1 cursor-pointer min-w-0"
          :title="node.custom_name || node.label"
          @click="startEditName"
        >
          <span class="text-sm font-semibold leading-snug break-words line-clamp-2">
            {{ node.custom_name || node.label }}
          </span>
          <Pencil class="size-3 shrink-0 text-muted-foreground opacity-0 group-hover:opacity-100 transition-opacity" />
        </div>
      </div>

      <!-- Confirm discard -->
      <template v-if="confirmClose">
        <span class="text-sm text-muted-foreground shrink-0">Discard changes?</span>
        <Button variant="destructive" size="xs" @click="emit('close')">Yes</Button>
        <Button variant="ghost" size="xs" @click="confirmClose = false">No</Button>
      </template>

      <Button v-else variant="ghost" size="icon-sm" title="Close" @click="tryClose">
        <X />
      </Button>
    </div>

    <!-- Fields -->
    <div class="flex-1 overflow-y-auto px-4 py-3 pb-scroll">

      <!-- Component info -->
      <div class="mb-4 pb-3 border-b text-xs text-muted-foreground">
        <div class="flex items-center gap-1">
          <span>Component: {{ node.label }}</span>
          <TooltipIcon :text="componentOf(node)?.description ?? ''" />
        </div>
        <!-- The registered id, module::path, so two components with one label can be told apart. -->
        <div class="mt-0.5 font-mono text-[11px] break-words select-all">
          <template v-for="(part, i) in componentId" :key="i"><wbr v-if="i">{{ part }}</template>
        </div>
      </div>

      <!-- Grouped: one row per tab, a click unfolds its settings underneath -->
      <Accordion
        v-if="shownTabs.length"
        type="multiple"
        class="-mx-4"
        :model-value="[...open]"
        @update:model-value="open = new Set(($event as string[] | undefined) ?? [])"
      >
        <AccordionItem v-for="name in shownTabs" :key="name" :value="name">
          <AccordionTrigger class="px-4 hover:no-underline hover:bg-accent rounded-none">{{ name }}</AccordionTrigger>
          <AccordionContent class="px-4 pt-2">
            <template v-for="(def, key) in settingsDef" :key="key">
              <div v-if="tabOf(def) === name && visible(def)" class="mb-4 last:mb-0 space-y-2">
                <div class="flex items-center gap-1">
                  <Label>{{ def.label }}</Label>
                  <TooltipIcon :text="String(def.description ?? '')" />
                </div>
                <component
                  :is="fieldComponents[def.type ?? ''] ?? fieldComponents.text"
                  :value="localSettings[key] ?? ''"
                  :def="def"
                  @update="updateField(String(key), $event)"
                />
              </div>
            </template>
          </AccordionContent>
        </AccordionItem>
      </Accordion>

      <!-- Schema-defined settings -->
      <template v-else-if="Object.keys(settingsDef).length > 0">
        <template v-for="(def, key) in settingsDef" :key="key">
          <div v-if="visible(def)" class="mb-4 space-y-2">
            <div class="flex items-center gap-1">
              <Label>{{ def.label }}</Label>
              <TooltipIcon :text="String(def.description ?? '')" />
            </div>
            <component
              :is="fieldComponents[def.type ?? ''] ?? fieldComponents.text"
              :value="localSettings[key] ?? ''"
              :def="def"
              @update="updateField(String(key), $event)"
            />
          </div>
        </template>
      </template>

      <div
        v-if="Object.keys(settingsDef).length === 0"
        class="text-center py-6 text-muted-foreground text-sm border border-dashed rounded-lg"
      >
        No settings defined
      </div>
    </div>

    <!-- Save button -->
    <div class="shrink-0 px-4 py-3 border-t">
      <p v-if="saveError" class="mb-2 text-sm text-destructive">{{ saveError }}</p>
      <Button class="w-full" :variant="hasChanges ? 'default' : 'outline'" :disabled="!hasChanges" @click="save">Save</Button>
    </div>
  </div>
</template>
