import vue from '@vitejs/plugin-vue'
import { resolve } from 'path'
import { defineConfig } from 'vitest/config'

// `npm test`; not part of `npm run build`. Without the build's plugins: no Tailwind, no copied vue/Phosphor assets.
export default defineConfig({
  plugins: [vue({ template: { compilerOptions: { isCustomElement: tag => tag.startsWith('cropper-') } } })],
  resolve: {
    alias: {
      '@': resolve(import.meta.dirname, 'src'),
      vuedraggable: 'vuedraggable/src/vuedraggable.js',
    },
  },
  test: {
    environment: 'happy-dom',
    include: ['test/**/*.test.ts'],
    setupFiles: ['test/setup.ts'],
    maxWorkers: 4,
  },
})
