
import { defineConfig } from 'vite'
import liveReload from 'vite-plugin-live-reload'

// https://vitejs.dev/config/
export default defineConfig({

  plugins: [
    // reloads the page when Bond files change
    liveReload('../../src/**/*.php', {
      root: __dirname,
    }),
  ],

  build: {
    // output dir for production build
    outDir: 'dist/admin-color-scheme',
    emptyOutDir: true,

    // emit manifest so PHP can find the hashed files
    manifest: true,

    rollupOptions: {
      // our entry
      input: 'admin-color-scheme.js',
    },
  },

  server: {
    // required to load scripts from custom host
    cors: true,

    // we need a strict port to match on PHP side
    strictPort: true,
    port: 2342, // if changed match on Settings/Admin
  },

})
