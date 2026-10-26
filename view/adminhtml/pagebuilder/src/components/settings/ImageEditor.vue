<script setup lang="ts">
import 'cropperjs'
import { computed, onMounted, reactive, ref } from 'vue'
import { FlipHorizontal2, FlipVertical2, RotateCcw, RotateCw } from '@lucide/vue'
import { Button } from '@/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogTitle } from '@/ui/dialog'
import { Input } from '@/ui/input'
import { Label } from '@/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import { Slider } from '@/ui/slider'

// The cropper elements as this file drives them
type CropperImage = HTMLElement & {
  $getTransform(): number[]
  $setTransform(a: number, b: number, c: number, d: number, e: number, f: number): void
  $center(size: string): void
  $resetTransform(): void
  $ready(): Promise<unknown>
}
type CropperSelection = HTMLElement & {
  aspectRatio: number
  $change(x: number, y: number, width: number, height: number): void
  $toCanvas(options: { width: number; beforeDraw(ctx: CanvasRenderingContext2D, el: HTMLCanvasElement): void }): Promise<HTMLCanvasElement>
}
type Adjustment = 'brightness' | 'contrast' | 'saturate'

/**
 * Edits one picture in the browser before it goes to Media/Upload
 */
const props = withDefaults(defineProps<{
  src: string
  name?: string
  type?: string
  // 'upload' also offers the file as it came; 'edit' only saves a copy.
  mode?: 'upload' | 'edit'
}>(), { name: 'image.png', type: 'image/png', mode: 'upload' })
const emit = defineEmits<{ apply: [file: File]; skip: []; cancel: [] }>()

const RATIOS = [
  { label: 'Free', value: 0 },
  { label: '1:1', value: 1 },
  { label: '4:3', value: 4 / 3 },
  { label: '16:9', value: 16 / 9 },
  { label: '3:4', value: 3 / 4 },
]
const FORMATS = [
  { label: 'Same as source', value: '' },
  { label: 'JPEG', value: 'image/jpeg' },
  { label: 'PNG', value: 'image/png' },
]
const ADJUSTMENTS: { key: Adjustment; label: string }[] = [
  { key: 'brightness', label: 'Brightness' },
  { key: 'contrast', label: 'Contrast' },
  { key: 'saturate', label: 'Saturation' },
]
const EXT: Record<string, string> = { 'image/jpeg': 'jpg', 'image/png': 'png' }
// The select refuses an empty value, which "Same as source" stores: it travels under this name instead
const SAME = '__same__'

const canvasRef    = ref<HTMLElement | null>(null)
const imageRef     = ref<CropperImage | null>(null)
const selectionRef = ref<CropperSelection | null>(null)
const ratio    = ref(0)
const adjust   = reactive({ brightness: 100, contrast: 100, saturate: 100 })
const maxWidth = ref<string | number>('')
const format   = ref('')
const quality  = ref(90)
// The selection measured in source pixels, for the size readout.
const crop  = reactive({ width: 0, height: 0 })
const busy  = ref(false)
const error = ref('')

const outType = computed(() => format.value || (EXT[props.type] ? props.type : 'image/png'))
const lossy   = computed(() => outType.value !== 'image/png')
const filter  = computed(() => `brightness(${adjust.brightness}%) contrast(${adjust.contrast}%) saturate(${adjust.saturate}%)`)
const outSize = computed(() => {
  const width  = Math.max(1, Math.round(crop.width))
  const height = Math.max(1, Math.round(crop.height))
  const max    = Math.round(Number(maxWidth.value))
  return max > 0 && max < width
    ? { width: max, height: Math.max(1, Math.round(height * max / width)) }
    : { width, height }
})

// The picture's box inside the canvas, in canvas pixels — where the selection may go.
function imageBounds() {
  const c = canvasRef.value!.getBoundingClientRect()
  const i = imageRef.value!.getBoundingClientRect()
  const x = Math.max(i.left, c.left) - c.left
  const y = Math.max(i.top, c.top) - c.top
  return {
    x, y,
    width:  Math.min(i.right, c.right) - c.left - x,
    height: Math.min(i.bottom, c.bottom) - c.top - y,
  }
}

// Canvas pixels per source pixel: the length of the transform's first column, whatever the rotation
function displayScale() {
  const [a = 1, b = 0] = imageRef.value!.$getTransform()
  return Math.hypot(a, b) || 1
}

function measure(width: number, height: number) {
  const s = displayScale()
  crop.width  = width / s
  crop.height = height / s
}

// Keeps the selection on the picture: a box reaching past it would export empty pixels.
function onSelectionChange(e: CustomEvent<{ x: number; y: number; width: number; height: number }>) {
  const { x, y, width, height } = e.detail
  const b = imageBounds()
  if (x < b.x - 1 || y < b.y - 1 || x + width > b.x + b.width + 1 || y + height > b.y + b.height + 1) {
    e.preventDefault()
    return
  }
  measure(width, height)
}

// The largest box of the chosen ratio, centred on the picture (the whole picture for Free).
function fitSelection() {
  const b = imageBounds()
  const r = ratio.value
  let { width, height } = b
  if (r) {
    width  = Math.min(b.width, b.height * r)
    height = width / r
  }
  const sel = selectionRef.value!
  sel.aspectRatio = r || NaN
  sel.$change(b.x + (b.width - width) / 2, b.y + (b.height - height) / 2, width, height)
  measure(width, height)
}

function setRatio(value: number) {
  ratio.value = value
  fitSelection()
}

/**
 * Applies a 2×2 transform in screen space (T × M), not in the picture's own axes as $rotate/$scale do
 */
function transformScreen(ta: number, tb: number, tc: number, td: number) {
  const img = imageRef.value!
  const [a = 1, b = 0, c = 0, d = 1, e = 0, f = 0] = img.$getTransform()
  img.$setTransform(ta * a + tc * b, tb * a + td * b, ta * c + tc * d, tb * c + td * d, ta * e + tc * f, tb * e + td * f)
  refit()
}
const rotateRight = () => transformScreen(0, 1, -1, 0)
const rotateLeft  = () => transformScreen(0, -1, 1, 0)
const flipX       = () => transformScreen(-1, 0, 0, 1)
const flipY       = () => transformScreen(1, 0, 0, -1)

function refit() {
  imageRef.value!.$center('contain')
  fitSelection()
}

function resetAll() {
  imageRef.value!.$resetTransform()
  Object.assign(adjust, { brightness: 100, contrast: 100, saturate: 100 })
  ratio.value = 0
  refit()
}

async function apply() {
  busy.value  = true
  error.value = ''
  try {
    const type = outType.value
    const canvas = await selectionRef.value!.$toCanvas({
      width: outSize.value.width,
      beforeDraw(ctx, el) {
        // JPEG has no alpha: paint white first, before the filter, so it stays white.
        if (type === 'image/jpeg') {
          ctx.fillStyle = '#fff'
          ctx.fillRect(0, 0, el.width, el.height)
        }
        ctx.imageSmoothingQuality = 'high'
        ctx.filter = filter.value
      },
    })
    const blob = await new Promise<Blob | null>(resolve => canvas.toBlob(resolve, type, lossy.value ? quality.value / 100 : undefined))
    if (!blob) throw new Error('empty')
    // Named after the type the browser encoded
    const base = props.name.replace(/\.[^.]+$/, '') || 'image'
    emit('apply', new File([blob], `${base}.${EXT[blob.type] || 'png'}`, { type: blob.type }))
  } catch {
    error.value = 'Could not export the image.'
  }
  busy.value = false
}

onMounted(async () => {
  try {
    await imageRef.value!.$ready()
    requestAnimationFrame(fitSelection)
  } catch {
    error.value = 'Could not load the image.'
  }
})
</script>

<template>
  <Dialog :open="true" @update:open="$event ? null : emit('cancel')">
    <DialogContent class="sm:max-w-[min(1100px,calc(100vw-2rem))] h-[min(720px,90vh)] p-0 gap-0 flex flex-col overflow-hidden">
      <!-- Header -->
      <div class="flex items-center gap-3 px-4 py-3 pr-12 border-b shrink-0">
        <DialogTitle class="text-base shrink-0">Edit image</DialogTitle>
        <DialogDescription class="flex-1 min-w-0 truncate" :title="name">{{ name }}</DialogDescription>
      </div>

      <div class="flex-1 min-h-0 flex">
        <!-- Stage -->
        <div class="flex-1 min-w-0 p-4 bg-muted" @wheel.capture.stop.prevent>
          <cropper-canvas ref="canvasRef" background style="display:block;width:100%;height:100%">
            <!-- translatable: $center() moves through $translate -->
            <cropper-image ref="imageRef" :src.attr="src" alt="" rotatable scalable translatable :style="{ filter }"></cropper-image>
            <cropper-shade hidden></cropper-shade>
            <cropper-handle action="select" plain></cropper-handle>
            <cropper-selection ref="selectionRef" movable resizable outlined @change="onSelectionChange">
              <cropper-grid role="grid" bordered covered></cropper-grid>
              <cropper-crosshair centered></cropper-crosshair>
              <!-- plain: no tint over the selection, it would skew the colour being judged. -->
              <cropper-handle action="move" plain></cropper-handle>
              <cropper-handle action="n-resize"></cropper-handle>
              <cropper-handle action="e-resize"></cropper-handle>
              <cropper-handle action="s-resize"></cropper-handle>
              <cropper-handle action="w-resize"></cropper-handle>
              <cropper-handle action="ne-resize"></cropper-handle>
              <cropper-handle action="nw-resize"></cropper-handle>
              <cropper-handle action="se-resize"></cropper-handle>
              <cropper-handle action="sw-resize"></cropper-handle>
            </cropper-selection>
          </cropper-canvas>
        </div>

        <!-- Controls -->
        <div class="w-72 shrink-0 border-l overflow-y-auto p-4 flex flex-col gap-5">
          <section>
            <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Crop</h4>
            <div class="grid grid-cols-5 gap-1">
              <Button
                v-for="r in RATIOS"
                :key="r.label"
                size="sm"
                :variant="ratio === r.value ? 'default' : 'outline'"
                class="px-0"
                @click="setRatio(r.value)"
              >{{ r.label }}</Button>
            </div>
          </section>

          <section>
            <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Rotate &amp; flip</h4>
            <div class="grid grid-cols-4 gap-1">
              <Button variant="outline" size="sm" title="Rotate left" @click="rotateLeft"><RotateCcw /></Button>
              <Button variant="outline" size="sm" title="Rotate right" @click="rotateRight"><RotateCw /></Button>
              <Button variant="outline" size="sm" title="Flip horizontal" @click="flipX"><FlipHorizontal2 /></Button>
              <Button variant="outline" size="sm" title="Flip vertical" @click="flipY"><FlipVertical2 /></Button>
            </div>
          </section>

          <section>
            <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Adjust</h4>
            <div class="flex flex-col gap-4">
              <!-- Double click puts the slider back to neutral -->
              <div v-for="a in ADJUSTMENTS" :key="a.key" class="space-y-2" @dblclick="adjust[a.key] = 100">
                <Label class="justify-between">
                  {{ a.label }}
                  <span class="tabular-nums font-normal text-muted-foreground">{{ adjust[a.key] - 100 }}</span>
                </Label>
                <Slider :model-value="[adjust[a.key]]" :min="0" :max="200" @update:model-value="adjust[a.key] = ($event ?? [100])[0]!" />
              </div>
            </div>
          </section>

          <section>
            <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Output</h4>
            <div class="flex flex-col gap-4">
              <div class="space-y-2">
                <Label>Max width (px)</Label>
                <Input v-model="maxWidth" type="number" min="1" :placeholder="String(Math.round(crop.width) || '')" />
              </div>
              <div class="space-y-2">
                <Label>Format</Label>
                <Select :model-value="format || SAME" @update:model-value="format = $event === SAME ? '' : String($event)">
                  <SelectTrigger class="w-full"><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="f in FORMATS" :key="f.value" :value="f.value || SAME">{{ f.label }}</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div v-if="lossy" class="space-y-2">
                <Label class="justify-between">
                  Quality
                  <span class="tabular-nums font-normal text-muted-foreground">{{ quality }}</span>
                </Label>
                <Slider :model-value="[quality]" :min="10" :max="100" @update:model-value="quality = ($event ?? [90])[0]!" />
              </div>
              <p class="text-sm text-muted-foreground tabular-nums">{{ outSize.width }} × {{ outSize.height }} px</p>
            </div>
          </section>

          <Button variant="link" class="self-start px-0" @click="resetAll">Reset all</Button>
        </div>
      </div>

      <!-- Footer -->
      <div class="flex items-center gap-2 px-4 py-3 border-t shrink-0">
        <span v-if="error" class="flex-1 min-w-0 truncate text-sm text-destructive">{{ error }}</span>
        <span v-else class="flex-1"></span>
        <Button variant="outline" @click="emit('cancel')">Cancel</Button>
        <Button v-if="mode === 'upload'" variant="outline" :disabled="busy" @click="emit('skip')">Upload original</Button>
        <Button :disabled="busy" @click="apply">{{ busy ? 'Saving…' : mode === 'upload' ? 'Apply & upload' : 'Save as new image' }}</Button>
      </div>
    </DialogContent>
  </Dialog>
</template>
