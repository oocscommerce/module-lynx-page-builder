<script setup lang="ts">
import { computed, defineAsyncComponent, nextTick, ref } from 'vue'
import { Check, ExternalLink, Folder, FolderPlus, Image as ImageIcon, Search, Upload, X } from '@lucide/vue'
import { Button } from '@/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogTitle } from '@/ui/dialog'
import { Input } from '@/ui/input'
import { usePageBuilderStore } from '@/stores/pageBuilder'
import type { FieldDef } from './types'

type MediaDir = { id: string; name: string }
type MediaFile = { path: string; name: string; thumb: string }
type Editing = { src: string; name: string; type: string; mode: 'upload' | 'edit'; file: Blob; resolve: (file: Blob | null) => void }

// Loaded with cropperjs the first time an image is edited.
const ImageEditor = defineAsyncComponent(() => import('./ImageEditor.vue'))

/**
 * An `image` setting: a path under pub/media, picked from the media browser or uploaded
 */
const props = withDefaults(defineProps<{ value?: string; def?: FieldDef }>(), { value: '' })
const emit = defineEmits<{ update: [value: string] }>()

const cfg = usePageBuilderStore().config
// The block leaves a URL empty when the admin lacks the media gallery permission
const canUpload = !!cfg.mediaUploadUrl
const canFolder = !!cfg.mediaFolderUrl

const modalOpen     = ref(false)
const confirmRemove = ref(false)
const loading       = ref(false)
const uploadLoading = ref(false)
const pathId        = ref('')
const parentId      = ref<string | null>(null)
const dirs          = ref<MediaDir[]>([])
const files         = ref<MediaFile[]>([])
const error         = ref('')
const search        = ref('')
const dragging      = ref(false)
const uploadRef     = ref<HTMLInputElement | null>(null)
// The new-folder box in the toolbar and the name typed into it
const folderOpen    = ref(false)
const folderName    = ref('')
const folderLoading = ref(false)
const folderInput   = ref<InstanceType<typeof Input> | null>(null)
// The editor open over everything: { src, name, type, mode, file, resolve }.
const editing       = ref<Editing | null>(null)

// What the editor can read and write back; anything else goes to Media/Upload as is
const EDITABLE = /^image\/(jpeg|png)$/

function getFormKey() {
  return (window as unknown as { FORM_KEY?: string }).FORM_KEY
    || document.cookie.split(';').map(c => c.trim()).find(c => c.startsWith('form_key='))?.slice(9)
    || ''
}

// Lists one folder; answers whether it could.
async function loadContents(pid: string | null = '') {
  loading.value = true
  error.value   = ''
  let ok = false
  try {
    const fd = new FormData()
    fd.append('form_key', getFormKey())
    if (pid) fd.append('path', pid)
    const res  = await fetch(cfg.mediaContentsUrl, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd })
    const data = await res.json()
    if (data.success) {
      pathId.value   = data.pathId
      parentId.value = data.parent
      dirs.value     = data.dirs
      files.value    = data.files
      search.value   = ''
      ok = true
    } else {
      error.value = data.error || 'Failed to load'
    }
  } catch {
    error.value = 'Network error'
  }
  loading.value = false
  return ok
}

// The path as clickable crumbs: 'wysiwyg/sale' → Media / wysiwyg / sale
const crumbs = computed(() => {
  const parts = (pathId.value || '').split('/').filter(Boolean)
  return [{ label: 'Media', id: '' }, ...parts.map((p, i) => ({ label: p, id: parts.slice(0, i + 1).join('/') }))]
})

const visibleDirs  = computed(() => filterByName(dirs.value))
const visibleFiles = computed(() => filterByName(files.value))
function filterByName<T extends { name: string }>(list: T[]) {
  const q = search.value.trim().toLowerCase()
  return q ? list.filter(x => x.name.toLowerCase().includes(q)) : list
}

// The folder holding the current picture ('' for none, or for an absolute URL).
const valueDir = computed(() => /^https?:\/\//.test(props.value || '') ? '' : (props.value || '').split('/').slice(0, -1).join('/'))

// Opens where the current picture lives; the media root when there is none or that folder is gone
async function openModal() {
  modalOpen.value = true
  if (!valueDir.value || !await loadContents(valueDir.value)) await loadContents()
}
function closeModal() { modalOpen.value = false; dragging.value = false }
function navigate(id: string | null) { loadContents(id) }

function toggleFolderBox() {
  folderOpen.value = !folderOpen.value
  if (folderOpen.value) nextTick(() => (folderInput.value?.$el as HTMLInputElement | undefined)?.focus())
}

// Creates a folder under the open one and lists it; the core answers with the name rules
async function createFolder() {
  const name = folderName.value.trim()
  if (!name || folderLoading.value) return
  folderLoading.value = true
  error.value = ''
  try {
    const fd = new FormData()
    fd.append('form_key', getFormKey())
    fd.append('name', name)
    if (pathId.value) fd.append('path', pathId.value)
    const res  = await fetch(cfg.mediaFolderUrl, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd })
    const data = await res.json()
    if (data.success) {
      folderOpen.value = false
      folderName.value = ''
      await loadContents(data.id)
    } else {
      error.value = data.error || 'Failed to create folder'
    }
  } catch {
    error.value = 'Network error'
  }
  folderLoading.value = false
}
function selectImage(file: MediaFile) { emit('update', file.path); closeModal() }
function clear() { emit('update', '') }

function previewUrl(path: string) {
  if (!path) return ''
  if (path.startsWith('http://') || path.startsWith('https://')) return path
  return cfg.mediaBaseUrl + '/' + path
}
const fileName = computed(() => (props.value || '').split('/').pop() ?? '')

/**
 * Uploads one file into the folder open in the browser (the media root when the browser is closed
 */
async function upload(file: Blob | null | undefined, intoPath: string): Promise<string | null> {
  if (!canUpload) return null
  if (!file || !file.type.startsWith('image/')) { error.value = 'Only images can be uploaded.'; return null }
  const fd = new FormData()
  fd.append('image', file)
  if (intoPath) fd.append('path', intoPath)
  fd.append('form_key', getFormKey())
  uploadLoading.value = true
  error.value = ''
  let stored: string | null = null
  try {
    const res  = await fetch(cfg.mediaUploadUrl, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd })
    const data = await res.json()
    if (data.success) stored = data.path
    else error.value = data.error || 'Upload failed'
  } catch {
    error.value = 'Upload error'
  }
  uploadLoading.value = false
  return stored
}

/**
 * Opens the editor on a picture and answers what to upload
 */
function openEditor(blob: Blob, name: string, mode: Editing['mode']) {
  return new Promise<Blob | null>(resolve => {
    editing.value = { src: URL.createObjectURL(blob), name, type: blob.type, mode, file: blob, resolve }
  })
}
function finishEdit(result: Blob | null) {
  if (!editing.value) return
  URL.revokeObjectURL(editing.value.src)
  editing.value.resolve(result)
  editing.value = null
}

// Every upload passes through the editor first, when it can read the file.
async function prepare(file: File | undefined): Promise<Blob | null | undefined> {
  return file && EDITABLE.test(file.type) ? openEditor(file, file.name, 'upload') : file
}

async function handleUploadInput(e: Event) {
  const input = e.target as HTMLInputElement
  const file = await prepare(input.files?.[0])
  input.value = ''
  if (file && await upload(file, pathId.value)) await loadContents(pathId.value)
}

// Drop onto the browser's grid: upload into the open folder and list it.
async function onDropInModal(e: DragEvent) {
  dragging.value = false
  const file = await prepare(e.dataTransfer?.files?.[0])
  if (file && await upload(file, pathId.value)) await loadContents(pathId.value)
}

// Drop onto the field itself: upload into the media root and use it right away.
async function onDropInField(e: DragEvent) {
  dragging.value = false
  const file = await prepare(e.dataTransfer?.files?.[0])
  const path = file && await upload(file, '')
  if (path) emit('update', path)
}

/**
 * Edits the picture in the field: fetched as a blob (so the editor's canvas stays untainted)
 */
const canEdit = computed(() => /\.(jpe?g|png)$/i.test(props.value || ''))
async function editCurrent() {
  error.value = ''
  let blob
  try {
    const res = await fetch(previewUrl(props.value))
    if (!res.ok) throw new Error(String(res.status))
    blob = await res.blob()
  } catch {
    error.value = 'Could not load this image for editing.'
    return
  }
  const file = await openEditor(blob, fileName.value, 'edit')
  if (!file) return
  const path = await upload(file, valueDir.value)
  if (path) emit('update', path)
}
</script>

<template>
  <div>
    <!-- Field: the picture, or a place to drop one -->
    <div v-if="value" class="group">
      <div class="relative">
        <img :src="previewUrl(value)" :alt="fileName" class="w-full h-24 object-cover rounded-md border bg-muted" />
        <Button
          as="a"
          :href="previewUrl(value)"
          target="_blank"
          rel="noopener"
          variant="outline"
          size="icon-sm"
          class="absolute top-1.5 right-1.5 opacity-0 group-hover:opacity-100 transition-opacity"
          title="Open image"
        >
          <ExternalLink />
        </Button>
      </div>
      <div class="mt-1.5 flex items-center gap-1 min-w-0">
        <!-- Remove asks first: the field is emptied, and there is no undo for it here. -->
        <template v-if="confirmRemove">
          <span class="flex-1 min-w-0 truncate text-sm text-muted-foreground select-none">Remove this image?</span>
          <Button variant="destructive" size="xs" @click.prevent="confirmRemove = false; clear()">Yes</Button>
          <Button variant="ghost" size="xs" @click.prevent="confirmRemove = false">No</Button>
        </template>
        <template v-else>
          <span class="flex-1 min-w-0 truncate text-sm text-muted-foreground" :title="value">{{ fileName }}</span>
          <Button v-if="canEdit && canUpload" variant="ghost" size="xs" :disabled="uploadLoading" @click.prevent="editCurrent">{{ uploadLoading ? 'Saving…' : 'Edit' }}</Button>
          <Button variant="ghost" size="xs" @click.prevent="openModal">Replace</Button>
          <Button variant="ghost" size="xs" class="hover:text-destructive" @click.prevent="confirmRemove = true">Remove</Button>
        </template>
      </div>
    </div>

    <button
      v-else
      type="button"
      class="w-full h-24 flex flex-col items-center justify-center gap-1 rounded-md border border-dashed cursor-pointer transition-colors text-sm outline-none focus-visible:ring-3 focus-visible:ring-ring/15"
      :class="dragging ? 'border-primary bg-accent text-foreground' : 'border-input text-muted-foreground hover:border-primary hover:text-foreground hover:bg-accent/50'"
      @click.prevent="openModal"
      @dragover.prevent="dragging = true"
      @dragleave.prevent="dragging = false"
      @drop.prevent="onDropInField"
    >
      <ImageIcon class="size-5" />
      <span class="font-medium">{{ uploadLoading ? 'Uploading…' : 'Choose image' }}</span>
      <span v-if="!uploadLoading && canUpload">or drop a file here</span>
    </button>
    <p v-if="error && !modalOpen" class="mt-1.5 text-sm text-destructive">{{ error }}</p>

    <!-- Media browser -->
    <Dialog :open="modalOpen" @update:open="$event ? null : closeModal()">
      <DialogContent class="sm:max-w-[min(960px,calc(100vw-2rem))] h-[min(640px,90vh)] p-0 gap-0 flex flex-col overflow-hidden" :show-close-button="false">
        <!-- Header: title, crumbs, new folder, upload, close -->
        <div class="flex items-center gap-3 px-4 py-3 border-b shrink-0">
          <DialogTitle class="text-base shrink-0">Media</DialogTitle>
          <DialogDescription class="sr-only">Pick an image from the media gallery or upload one</DialogDescription>
          <nav class="flex-1 min-w-0 flex items-center gap-0.5 text-sm truncate">
            <template v-for="(c, i) in crumbs" :key="c.id">
              <span v-if="i" class="text-muted-foreground">/</span>
              <Button
                variant="ghost"
                size="xs"
                class="truncate"
                :class="i === crumbs.length - 1 ? 'font-semibold disabled:opacity-100' : 'text-muted-foreground'"
                :disabled="i === crumbs.length - 1"
                @click="navigate(c.id)"
              >{{ c.label }}</Button>
            </template>
          </nav>
          <Button v-if="canFolder" variant="outline" :class="folderOpen ? 'bg-accent' : ''" @click="toggleFolderBox">
            <FolderPlus />
            New folder
          </Button>
          <template v-if="canUpload">
            <Button :disabled="uploadLoading" @click="uploadRef?.click()">
              <Upload />
              {{ uploadLoading ? 'Uploading…' : 'Upload' }}
            </Button>
            <input ref="uploadRef" type="file" accept="image/jpeg,image/png" class="hidden" :disabled="uploadLoading" @change="handleUploadInput" />
          </template>
          <Button variant="ghost" size="icon-sm" title="Close" @click="closeModal">
            <X />
          </Button>
        </div>

        <!-- Toolbar: search, new folder -->
        <div class="px-4 py-2 border-b shrink-0 flex items-center gap-3">
          <div class="relative flex-1 max-w-xs">
            <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 size-4 text-muted-foreground pointer-events-none" />
            <Input v-model="search" type="text" placeholder="Search this folder…" class="pl-8" />
          </div>
          <form v-if="folderOpen" class="flex items-center gap-2" @submit.prevent="createFolder">
            <Input
              ref="folderInput"
              v-model="folderName"
              type="text"
              placeholder="Folder name"
              :disabled="folderLoading"
              class="w-44"
              @keydown.esc.stop="folderOpen = false"
            />
            <Button type="submit" :disabled="folderLoading || !folderName.trim()">{{ folderLoading ? 'Creating…' : 'Create' }}</Button>
          </form>
          <span class="text-sm text-muted-foreground">{{ visibleFiles.length }} image{{ visibleFiles.length === 1 ? '' : 's' }}<template v-if="visibleDirs.length"> · {{ visibleDirs.length }} folder{{ visibleDirs.length === 1 ? '' : 's' }}</template></span>
          <span v-if="error" class="ml-auto text-sm text-destructive">{{ error }}</span>
        </div>

        <!-- Grid, also the drop target -->
        <div
          class="relative flex-1 min-h-0 overflow-y-auto p-4"
          @dragover.prevent="dragging = true"
          @dragleave.prevent="dragging = false"
          @drop.prevent="onDropInModal"
        >
          <div v-if="loading" class="flex items-center justify-center h-full text-muted-foreground text-sm">Loading…</div>

          <div v-else-if="visibleDirs.length || visibleFiles.length" class="grid grid-cols-6 gap-3">
            <button
              v-for="dir in visibleDirs"
              :key="dir.id"
              type="button"
              class="group flex flex-col gap-1.5 text-left p-0 cursor-pointer outline-none"
              :title="dir.name"
              @click="navigate(dir.id)"
            >
              <div class="w-full aspect-square rounded-md border bg-muted/50 group-hover:border-primary group-hover:bg-accent group-focus-visible:ring-3 group-focus-visible:ring-ring/15 flex items-center justify-center transition-colors">
                <Folder class="size-9 text-muted-foreground group-hover:text-foreground transition-colors" />
              </div>
              <span class="w-full truncate text-sm text-muted-foreground group-hover:text-foreground">{{ dir.name }}</span>
            </button>

            <button
              v-for="file in visibleFiles"
              :key="file.path"
              type="button"
              class="group flex flex-col gap-1.5 text-left p-0 cursor-pointer outline-none"
              :title="file.name"
              @click="selectImage(file)"
            >
              <div
                class="relative w-full aspect-square rounded-md overflow-hidden bg-muted border transition-colors group-focus-visible:ring-3 group-focus-visible:ring-ring/15"
                :class="file.path === value ? 'border-primary ring-2 ring-primary' : 'group-hover:border-primary'"
              >
                <img :src="file.thumb" :alt="file.name" class="w-full h-full object-cover" loading="lazy" />
                <span v-if="file.path === value" class="absolute top-1.5 right-1.5 size-5 rounded-full bg-primary text-primary-foreground flex items-center justify-center shadow-sm">
                  <Check class="size-3" />
                </span>
              </div>
              <span class="w-full truncate text-sm text-muted-foreground group-hover:text-foreground">{{ file.name }}</span>
            </button>
          </div>

          <div v-else class="flex flex-col items-center justify-center h-full gap-2 text-muted-foreground text-sm">
            <ImageIcon class="size-8" />
            <span>{{ search ? 'Nothing matches your search' : 'No images here yet' }}</span>
            <Button v-if="!search && canUpload" variant="outline" class="mt-1" @click="uploadRef?.click()">Upload an image</Button>
          </div>

          <!-- Drop overlay -->
          <div v-if="dragging && canUpload" class="absolute inset-2 rounded-lg border-2 border-dashed border-primary bg-background/80 flex items-center justify-center text-sm font-medium pointer-events-none">
            Drop to upload here
          </div>
        </div>
      </DialogContent>
    </Dialog>

    <ImageEditor
      v-if="editing"
      :src="editing.src"
      :name="editing.name"
      :type="editing.type"
      :mode="editing.mode"
      @apply="finishEdit"
      @skip="finishEdit(editing.file)"
      @cancel="finishEdit(null)"
    />
  </div>
</template>
