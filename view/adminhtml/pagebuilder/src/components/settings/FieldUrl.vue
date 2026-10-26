<script setup lang="ts">
import { ref, computed } from 'vue'
import { ExternalLink, Link } from '@lucide/vue'
import { Input } from '@/ui/input'
import type { FieldDef } from './types'

const props = defineProps<{ value?: string; def?: FieldDef }>()
defineEmits<{ update: [value: string] }>()

const touched = ref(false)

// A store path (`about-us`, `checkout/cart`, `#top`) that the provider turns into the store URL
const absolute = computed(() => /^[a-z][a-z0-9+.-]*:/i.test(props.value ?? ''))

const isValid = computed(() => {
  if (!props.value || !absolute.value) return true
  try { new URL(props.value); return true } catch { return false }
})

const showError = computed(() => touched.value && !isValid.value)
</script>

<template>
  <div>
    <div class="relative">
      <Link class="absolute left-2.5 top-1/2 -translate-y-1/2 size-4 text-muted-foreground pointer-events-none" :class="showError ? 'text-destructive' : ''" />
      <Input
        :model-value="value ?? ''"
        type="text"
        :placeholder="def?.placeholder ?? 'about-us or https://…'"
        :aria-invalid="showError || undefined"
        class="pl-8"
        :class="value && absolute && isValid ? 'pr-9' : ''"
        @update:model-value="$emit('update', String($event))"
        @blur="touched = true"
      />
      <!-- Only an absolute URL opens from here: a store path has no store in the admin. -->
      <a
        v-if="value && absolute && isValid"
        :href="value"
        target="_blank"
        rel="noopener"
        class="absolute right-1.5 top-1/2 -translate-y-1/2 flex items-center justify-center size-6 rounded-sm text-muted-foreground hover:text-foreground hover:bg-accent transition-colors"
        title="Open URL"
        @click.stop
      >
        <ExternalLink class="size-3.5" />
      </a>
    </div>
    <p v-if="showError" class="mt-1.5 text-sm text-destructive">Invalid URL</p>
  </div>
</template>
