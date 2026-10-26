import { defineAsyncComponent } from 'vue'

import FieldText from './FieldText.vue'
import FieldTextarea from './FieldTextarea.vue'
import FieldNumber from './FieldNumber.vue'
import FieldToggle from './FieldToggle.vue'
import FieldSelect from './FieldSelect.vue'
import FieldImage from './FieldImage.vue'
import FieldUrl from './FieldUrl.vue'
import FieldArray from './FieldArray.vue'
import FieldI18n from './FieldI18n.vue'

export default {
  text: FieldText,
  textarea: FieldTextarea,
  number: FieldNumber,
  toggle: FieldToggle,
  select: FieldSelect,
  // Loaded with its picker (vanilla-colorful) the first time a color setting is opened.
  color: defineAsyncComponent(() => import('./FieldColor.vue')),
  // Loaded the first time a component with a richtext setting is opened
  richtext: defineAsyncComponent(() => import('./FieldRichText.vue')),
  image: FieldImage,
  url: FieldUrl,
  array: FieldArray,
  // Localized text: the base field for the default text, translations in a dialog.
  text_i18n: FieldI18n,
  textarea_i18n: FieldI18n,
  richtext_i18n: FieldI18n,
}
