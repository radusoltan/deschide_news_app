import { fileURLToPath } from 'url'
import { dirname, join } from 'path'
import { cp } from 'fs/promises'

const __filename = fileURLToPath(import.meta.url)
const __dirname = dirname(__filename)

const sourceDir = join(__dirname, '../node_modules/tinymce')
const destinationDir = join(__dirname, '../public/tinymce')

try {
  await cp(sourceDir, destinationDir, { recursive: true })
  console.log('✅ TinyMCE files copied to public/tinymce successfully')
} catch (error) {
  console.error('❌ Failed to copy TinyMCE files:', error)
  process.exit(1)
}
