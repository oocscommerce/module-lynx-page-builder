<script setup lang="ts">
import { ref, watch } from 'vue'
import { Eye, EyeOff } from '@lucide/vue'
import { Button } from '@/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/ui/dialog'
import { Input } from '@/ui/input'
import { Label } from '@/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'

// Share preview: { allowed, listUrl, createUrl, revokeUrl, hours } from Block\Adminhtml\PageBuilder::getShareJson()
const props = defineProps<{ share: Record<string, any> }>()
const emit = defineEmits<{ notify: [type: string, text: string] }>()
const open = defineModel<boolean>('open', { default: false })

const sharePassword = ref('')
// Masked as typed, like any password field; the eye shows it for checking
const sharePasswordVisible = ref(false)
const shareHours = ref<any>(null)
const shareBusy = ref(false)
// The link just made: { url, password, expires_at }; null until Create, and again once the dialog closes
const shareResult = ref<any>(null)
// The password of the link just made, masked until the eye is clicked; Copy works either way.
const shareResultVisible = ref(false)
const shareList = ref<any[]>([])

const SHARE_ALPHABET = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789'

// Ten characters without the ones that read alike (l/1/I, o/0/O)
function generateSharePassword() {
  const bytes = crypto.getRandomValues(new Uint8Array(10))
  sharePassword.value = Array.from(bytes, b => SHARE_ALPHABET[b % SHARE_ALPHABET.length]).join('')
  sharePasswordVisible.value = true
}

// Every opening starts clean
watch(open, async (isOpen) => {
  if (!isOpen) return
  sharePassword.value = ''
  sharePasswordVisible.value = false
  // A day by default: long enough to be opened later today or tomorrow morning.
  shareHours.value = props.share.hours?.includes(24) ? 24 : (props.share.hours?.[0] ?? null)
  shareResult.value = null
  await refreshShareList()
}, { immediate: true })

async function refreshShareList() {
  if (!props.share.listUrl) return
  try {
    const res = await fetch(props.share.listUrl, { headers: { Accept: 'application/json' } })
    const data: any = res.ok ? await res.json().catch(() => null) : null
    shareList.value = Array.isArray(data?.shares) ? data.shares : []
  } catch {
    shareList.value = []
  }
}

async function createShare() {
  if (shareBusy.value || !props.share.createUrl) return
  shareBusy.value = true
  try {
    const body = new FormData()
    body.append('form_key', (window as any).FORM_KEY ?? '')
    body.append('password', sharePassword.value)
    body.append('hours', String(shareHours.value ?? ''))
    const res = await fetch(props.share.createUrl, { method: 'POST', body, headers: { Accept: 'application/json' } })
    const data: any = await res.json().catch(() => null)
    if (!res.ok || !data?.success) {
      emit('notify', 'error', data?.message || 'The link could not be made.')
      return
    }
    shareResult.value = data
    shareResultVisible.value = false
    sharePassword.value = ''
    await refreshShareList()
  } catch (e: any) {
    emit('notify', 'error', e?.message || 'The server could not be reached.')
  } finally {
    shareBusy.value = false
  }
}

async function revokeShare(id) {
  if (shareBusy.value || !props.share.revokeUrl) return
  shareBusy.value = true
  try {
    const body = new FormData()
    body.append('form_key', (window as any).FORM_KEY ?? '')
    body.append('id', String(id))
    const res = await fetch(props.share.revokeUrl, { method: 'POST', body, headers: { Accept: 'application/json' } })
    const data: any = await res.json().catch(() => null)
    if (!res.ok || !data?.success) {
      emit('notify', 'error', data?.message || 'The link could not be revoked.')
    }
    // The link just made is gone too if it was the one revoked: nothing to copy any more.
    if (shareResult.value?.id === id) shareResult.value = null
    await refreshShareList()
  } catch (e: any) {
    emit('notify', 'error', e?.message || 'The server could not be reached.')
  } finally {
    shareBusy.value = false
  }
}

async function copyShareText(text) {
  try {
    await navigator.clipboard.writeText(text)
    emit('notify', 'success', 'Copied.')
  } catch {
    emit('notify', 'error', 'Could not copy. Select the text and copy it yourself.')
  }
}

// The select's labels: whole days read as days, the rest as hours.
function formatShareHours(hours) {
  if (hours % 24 === 0 && hours >= 24) {
    const days = hours / 24
    return days === 1 ? '1 day' : `${days} days`
  }
  return `${hours} hours`
}

// The span a link was made for, read back from its two timestamps
function shareSpan(s) {
  const hours = Math.round((new Date(s.expires_at).getTime() - new Date(s.created_at).getTime()) / 3600000)
  return hours > 0 ? formatShareHours(hours) : ''
}
</script>

<template>
  <!-- Share preview: a password-protected link to the saved design for a few hours -->
  <Dialog v-model:open="open">
    <DialogContent class="sm:max-w-lg">
      <DialogHeader>
        <DialogTitle>Share preview</DialogTitle>
        <DialogDescription>A link with a password that shows the saved design to someone without an admin login. Unsaved changes are not in it. Save first, or share a copy of the design.</DialogDescription>
      </DialogHeader>

      <div v-if="shareResult" class="grid gap-2">
        <div class="flex items-center gap-2">
          <Input readonly class="flex-1 font-mono text-xs" :model-value="shareResult.url" @focus="($event.target as HTMLInputElement).select()" />
          <Button variant="secondary" @click="copyShareText(shareResult.url)">Copy link</Button>
        </div>
        <div class="flex items-center gap-2">
          <div class="relative flex-1">
            <Input
              readonly
              :type="shareResultVisible ? 'text' : 'password'"
              class="pr-10 font-mono text-xs"
              :model-value="shareResult.password"
              @focus="($event.target as HTMLInputElement).select()"
            />
            <Button
              variant="ghost"
              size="icon-sm"
              class="absolute right-0.5 top-1/2 -translate-y-1/2"
              :title="shareResultVisible ? 'Hide password' : 'Show password'"
              @click="shareResultVisible = !shareResultVisible"
            ><EyeOff v-if="shareResultVisible" /><Eye v-else /></Button>
          </div>
          <Button variant="secondary" @click="copyShareText(shareResult.password)">Copy password</Button>
        </div>
        <p class="text-sm text-muted-foreground">Works until {{ shareResult.expires_label }}.</p>
      </div>

      <div v-else class="grid gap-4">
        <div class="grid gap-2">
          <Label for="pb-share-password">Password</Label>
          <div class="flex items-center gap-2">
            <div class="relative flex-1">
              <Input
                id="pb-share-password"
                v-model="sharePassword"
                :type="sharePasswordVisible ? 'text' : 'password'"
                autocomplete="new-password"
                spellcheck="false"
                class="pr-10"
                placeholder="At least 8 characters"
              />
              <Button
                variant="ghost"
                size="icon-sm"
                class="absolute right-0.5 top-1/2 -translate-y-1/2"
                :title="sharePasswordVisible ? 'Hide password' : 'Show password'"
                @click="sharePasswordVisible = !sharePasswordVisible"
              ><EyeOff v-if="sharePasswordVisible" /><Eye v-else /></Button>
            </div>
            <Button variant="secondary" @click="generateSharePassword">Generate</Button>
          </div>
        </div>
        <div class="grid gap-2">
          <Label>Works for</Label>
          <Select
            :model-value="shareHours == null ? undefined : String(shareHours)"
            @update:model-value="shareHours = Number($event)"
          >
            <SelectTrigger class="w-full"><SelectValue /></SelectTrigger>
            <SelectContent>
              <SelectItem v-for="h in (share.hours ?? [])" :key="h" :value="String(h)">{{ formatShareHours(h) }}</SelectItem>
            </SelectContent>
          </Select>
        </div>
      </div>

      <div v-if="shareList.length" class="border-t pt-3">
        <p class="text-sm font-medium mb-2">Links that still work</p>
        <ul class="grid gap-1.5">
          <li v-for="s in shareList" :key="s.id" class="flex items-center justify-between gap-3 text-sm">
            <span>
              <span class="inline-block min-w-14 font-medium">{{ shareSpan(s) }}</span>
              <span class="text-muted-foreground">until {{ s.expires_label }}</span>
            </span>
            <Button variant="outline" size="xs" class="hover:text-destructive" :disabled="shareBusy" @click="revokeShare(s.id)">Revoke</Button>
          </li>
        </ul>
      </div>

      <DialogFooter>
        <Button variant="outline" @click="open = false">{{ shareResult ? 'Done' : 'Cancel' }}</Button>
        <Button
          v-if="!shareResult"
          :disabled="shareBusy || sharePassword.length < 8 || !shareHours"
          @click="createShare"
        >Create link</Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
