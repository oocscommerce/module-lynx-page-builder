<script setup lang="ts">
import { useEditor, EditorContent } from '@tiptap/vue-3'
import Document from '@tiptap/extension-document'
import Paragraph from '@tiptap/extension-paragraph'
import Text from '@tiptap/extension-text'
import Bold from '@tiptap/extension-bold'
import Italic from '@tiptap/extension-italic'
import Underline from '@tiptap/extension-underline'
import Strike from '@tiptap/extension-strike'
import Heading from '@tiptap/extension-heading'
import BulletList from '@tiptap/extension-bullet-list'
import OrderedList from '@tiptap/extension-ordered-list'
import ListItem from '@tiptap/extension-list-item'
import {
  Bold as BoldIcon, Heading1, Heading2, Heading3, Heading4, Heading5, Heading6, Italic as ItalicIcon,
  List, ListOrdered, RemoveFormatting, Strikethrough, Underline as UnderlineIcon,
} from '@lucide/vue'
import { watch } from 'vue'
import { Separator } from '@/ui/separator'
import { Toggle } from '@/ui/toggle'
import type { FieldDef } from './types'

const props = withDefaults(defineProps<{ value?: string; def?: FieldDef }>(), { value: '' })
const emit = defineEmits<{ update: [value: string] }>()

const editor = useEditor({
  content: props.value,
  extensions: [Document, Paragraph, Text, Bold, Italic, Underline, Strike, Heading, BulletList, OrderedList, ListItem],
  onUpdate: ({ editor }) => emit('update', editor.getHTML()),
})

watch(() => props.value, (val) => {
  if (editor.value && editor.value.getHTML() !== val) {
    editor.value.commands.setContent(val, { emitUpdate: false })
  }
})

type Level = 1 | 2 | 3 | 4 | 5 | 6
const MARKS = [
  { icon: BoldIcon, cmd: 'toggleBold', active: 'bold', title: 'Bold' },
  { icon: ItalicIcon, cmd: 'toggleItalic', active: 'italic', title: 'Italic' },
  { icon: UnderlineIcon, cmd: 'toggleUnderline', active: 'underline', title: 'Underline' },
  { icon: Strikethrough, cmd: 'toggleStrike', active: 'strike', title: 'Strike' },
] as const
const HEADINGS: { icon: unknown; level: Level }[] = [
  { icon: Heading1, level: 1 }, { icon: Heading2, level: 2 }, { icon: Heading3, level: 3 },
  { icon: Heading4, level: 4 }, { icon: Heading5, level: 5 }, { icon: Heading6, level: 6 },
]
const LISTS = [
  { icon: List, cmd: 'toggleBulletList', active: 'bulletList', title: 'Bullet list' },
  { icon: ListOrdered, cmd: 'toggleOrderedList', active: 'orderedList', title: 'Ordered list' },
] as const

function run(cmd: (typeof MARKS)[number]['cmd'] | (typeof LISTS)[number]['cmd']) {
  editor.value?.chain().focus()[cmd]().run()
}
</script>

<template>
  <div class="border rounded-md overflow-hidden bg-background shadow-xs transition-[color,box-shadow] focus-within:border-ring focus-within:outline-2 focus-within:outline-solid focus-within:-outline-offset-1 focus-within:outline-ring">
    <!-- Toolbar; mousedown is kept from the buttons so the text keeps its selection -->
    <div class="flex flex-wrap items-center gap-0.5 p-1 border-b bg-muted/50" @mousedown.prevent>
      <Toggle
        v-for="action in MARKS"
        :key="action.cmd"
        size="sm"
        :title="action.title"
        :model-value="editor?.isActive(action.active) ?? false"
        @update:model-value="run(action.cmd)"
      ><component :is="action.icon" /></Toggle>

      <Separator orientation="vertical" class="mx-0.5 data-[orientation=vertical]:h-5" />

      <Toggle
        v-for="h in HEADINGS"
        :key="h.level"
        size="sm"
        :title="`Heading ${h.level}`"
        :model-value="editor?.isActive('heading', { level: h.level }) ?? false"
        @update:model-value="editor?.chain().focus().toggleHeading({ level: h.level }).run()"
      ><component :is="h.icon" /></Toggle>

      <Separator orientation="vertical" class="mx-0.5 data-[orientation=vertical]:h-5" />

      <Toggle
        v-for="action in LISTS"
        :key="action.cmd"
        size="sm"
        :title="action.title"
        :model-value="editor?.isActive(action.active) ?? false"
        @update:model-value="run(action.cmd)"
      ><component :is="action.icon" /></Toggle>

      <Separator orientation="vertical" class="mx-0.5 data-[orientation=vertical]:h-5" />

      <Toggle
        size="sm"
        title="Clear formatting"
        :model-value="false"
        @update:model-value="editor?.chain().focus().clearNodes().unsetAllMarks().run()"
      ><RemoveFormatting /></Toggle>
    </div>

    <EditorContent
      :editor="editor"
      class="px-3 py-2 min-h-24 text-sm leading-relaxed [&_.tiptap]:outline-none [&_.tiptap]:min-h-20 [&_.tiptap_p]:my-1 [&_.tiptap_h1]:text-xl [&_.tiptap_h1]:font-bold [&_.tiptap_h2]:text-lg [&_.tiptap_h2]:font-bold [&_.tiptap_h3]:text-base [&_.tiptap_h3]:font-bold [&_.tiptap_h4]:text-sm [&_.tiptap_h4]:font-bold [&_.tiptap_h5]:text-xs [&_.tiptap_h5]:font-bold [&_.tiptap_h6]:text-xs [&_.tiptap_h6]:font-bold [&_.tiptap_ul]:list-disc [&_.tiptap_ul]:pl-4 [&_.tiptap_ol]:list-decimal [&_.tiptap_ol]:pl-4"
    />
  </div>
</template>
