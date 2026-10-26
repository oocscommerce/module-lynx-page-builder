import { defineConfig } from 'vite'
import tailwindcss from '@tailwindcss/vite'
import vue from '@vitejs/plugin-vue'
import { readFileSync } from 'fs'
import { createRequire } from 'module'
import { resolve } from 'path'

// Vue is external: core and extension bundles resolve `vue` via import map to this one file (two copies cannot share a tree)
function sharedVue() {
  const require = createRequire(import.meta.url)
  return {
    name: 'lynx-shared-vue',
    generateBundle() {
      this.emitFile({
        type: 'asset',
        fileName: 'lynx-vue.min.js',
        source: readFileSync(require.resolve('vue/dist/vue.runtime.esm-browser.prod.js')),
      })
    },
  }
}

// Phosphor icons (woff2 + classes) beside the bundle, linked by pagebuilder.phtml:
// an inline @font-face would resolve url() against the admin page
function phosphorIcons() {
  const require = createRequire(import.meta.url)
  // The package's exports map hides package.json; the stylesheet is the entry point.
  const dir = resolve(require.resolve('@phosphor-icons/web/regular'), '..')
  return {
    name: 'lynx-phosphor-icons',
    generateBundle() {
      const css = readFileSync(resolve(dir, 'style.css'), 'utf8')
        // woff2 is enough for every browser the admin supports; drop the fallbacks.
        .replace(/src:\s*url\("\.\/Phosphor\.woff2"\) format\("woff2"\),[^;]*;/, 'src: url("./Phosphor.woff2") format("woff2");')
        .replace(/\/\*[\s\S]*?\*\//g, '')
        .replace(/\s+/g, ' ')
        .replace(/ ?([{}:;,]) ?/g, '$1')
      this.emitFile({ type: 'asset', fileName: 'phosphor.min.css', source: css })
      this.emitFile({ type: 'asset', fileName: 'Phosphor.woff2', source: readFileSync(resolve(dir, 'Phosphor.woff2')) })
    },
  }
}

export default defineConfig({
  plugins: [
    tailwindcss(),
    // The crop box is custom elements (<cropper-canvas> …), not Vue components
    vue({ template: { compilerOptions: { isCustomElement: tag => tag.startsWith('cropper-') } } }),
    sharedVue(),
    phosphorIcons(),
  ],

  base: './',

  build: {
    // Only the output lives in web/: setup:static-content:deploy copies everything under view/<area>/web,
    // node_modules included, so the workspace stays beside it.
    outDir: resolve(import.meta.dirname, '../web/js/pagebuilder'),
    emptyOutDir: true,
    target: 'esnext',
    minify: true,
    chunkSizeWarningLimit: 1024,
    // One stylesheet for the whole editor, lazy chunks included: nothing loads CSS at runtime
    cssCodeSplit: false,

    rollupOptions: {
      // api.ts: @pagebuilder/api for extensions
      input: {
        pagebuilder: resolve(import.meta.dirname, 'src/main.ts'),
        'pagebuilder-api': resolve(import.meta.dirname, 'src/api.ts'),
      },
      external: ['vue'],
      // An app build drops an entry's exports otherwise
      preserveEntrySignatures: 'exports-only',
      output: {
        format: 'es',
        comments: { legal: true, jsdoc: false },
        entryFileNames: 'lynx-[name].min.js',
        chunkFileNames: (chunk) => (chunk.name === 'pageBuilder' ? 'lynx-stores.min.js' : 'lynx-[name].min.js'),
        assetFileNames: (asset) => (asset.names?.[0]?.endsWith('.css') ? 'pagebuilder.min.css' : '[name][extname]'),
      },
    },
  },

  resolve: {
    alias: {
      '@': resolve(import.meta.dirname, 'src'),
      // The package entry is UMD and `require("vue")`, which with vue external leaves a runtime shim that throws;
      // the shipped ESM source imports vue through the import map like everything else.
      vuedraggable: 'vuedraggable/src/vuedraggable.js',
    },
  },
})
