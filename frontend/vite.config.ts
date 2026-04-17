import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// https://vite.dev/config/
export default defineConfig({
  base: './',
  plugins: [react()],
  server: {
    port: 5173,
    strictPort: true,
    proxy: {
      // Forward all /tudu_haute_couture_tech/* requests to Apache (port 80)
      // This eliminates CORS entirely in development — browser only talks to :5173
      '/tudu_haute_couture_tech': {
        target: 'http://localhost',
        changeOrigin: true,
      }
    }
  }
})

