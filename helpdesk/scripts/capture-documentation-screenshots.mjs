#!/usr/bin/env node
/**
 * Capture Helpdesk UI screenshots for documentation.
 *
 * Usage:
 *   npm run docs:screenshots
 *   HELPDESK_DOC_BASE_URL=https://cbp.africacdc.org/staff/helpdesk npm run docs:screenshots
 *   HELPDESK_DOC_TOKEN=<staff-jwt> npm run docs:screenshots   # authenticated pages
 *
 * Output: documentation/screenshots/*.png
 */
import { mkdir, writeFile } from 'node:fs/promises'
import path from 'node:path'
import { fileURLToPath } from 'node:url'
import { chromium } from 'playwright'

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const root = path.resolve(__dirname, '..')
const outDir = path.join(root, 'documentation', 'screenshots')

const baseUrl = (process.env.HELPDESK_DOC_BASE_URL || 'http://localhost/staff/helpdesk').replace(/\/$/, '')
const token = process.env.HELPDESK_DOC_TOKEN || ''
const skipAuthOnly = process.env.HELPDESK_DOC_SKIP_AUTH_ONLY === '1'
const viewport = { width: 1440, height: 900 }

/** @type {{ file: string, path: string, public?: boolean, waitMs?: number }[]} */
const shots = [
  { file: '01-home-knowledge-base', path: '/', waitMs: 2500 },
  { file: '02-create-ticket', path: '/tickets/new', waitMs: 2000 },
  { file: '03-my-tickets', path: '/tickets', waitMs: 2000 },
  { file: '04-ticket-detail', path: '/tickets/1', waitMs: 2000 },
  { file: '05-agent-desk', path: '/desk/agent', waitMs: 2500 },
  { file: '06-knowledge-base-manage', path: '/knowledge-base/manage', waitMs: 2000 },
  { file: '07-reports', path: '/reports', waitMs: 2000 },
  { file: '08-settings-general', path: '/settings/general', waitMs: 2000 },
  { file: '09-settings-ai', path: '/settings/ai', waitMs: 1500 },
  { file: '10-settings-agents', path: '/settings/agents', waitMs: 2000 },
  { file: '11-settings-categories', path: '/settings/categories', waitMs: 1500 },
  { file: '12-settings-jobs', path: '/settings/jobs', waitMs: 1500 },
  { file: '13-settings-integrations', path: '/settings/integrations', waitMs: 1500 },
  { file: '14-settings-logging', path: '/settings/logging', waitMs: 1500 },
  { file: '15-tv-screen', path: '/screen', public: true, waitMs: 3000 },
]

async function capture() {
  await mkdir(outDir, { recursive: true })

  const browser = await chromium.launch({ headless: true })
  const context = await browser.newContext({ viewport })
  const page = await context.newPage()

  const manifest = []
  const errors = []

  if (token) {
    const loginUrl = `${baseUrl}/?token=${encodeURIComponent(token)}`
    console.log(`Signing in via SSO token at ${baseUrl}/`)
    await page.goto(loginUrl, { waitUntil: 'networkidle', timeout: 60000 })
    await page.waitForTimeout(2000)
  } else {
    console.warn('HELPDESK_DOC_TOKEN not set — only public routes will capture cleanly.')
  }

  for (const shot of shots) {
    if (!shot.public && !token && skipAuthOnly) {
      errors.push(`${shot.file}: skipped (no HELPDESK_DOC_TOKEN)`)
      continue
    }

    const url = `${baseUrl}${shot.path}`
    const dest = path.join(outDir, `${shot.file}.png`)

    try {
      console.log(`Capturing ${shot.file} ← ${url}`)
      await page.goto(url, { waitUntil: 'networkidle', timeout: 60000 })
      await page.waitForTimeout(shot.waitMs ?? 1500)
      await page.screenshot({ path: dest, fullPage: shot.public === true })
      manifest.push({ file: `${shot.file}.png`, route: shot.path, url })
    } catch (err) {
      const msg = err instanceof Error ? err.message : String(err)
      errors.push(`${shot.file}: ${msg}`)
      console.error(`  ✗ ${shot.file}: ${msg}`)
    }
  }

  await browser.close()

  await writeFile(
    path.join(outDir, 'manifest.json'),
    JSON.stringify({ captured_at: new Date().toISOString(), baseUrl, manifest, errors }, null, 2),
  )

  console.log(`\nDone. ${manifest.length} screenshot(s) → ${outDir}`)
  if (errors.length) {
    console.log('Skipped / failed:')
    for (const e of errors) console.log(`  - ${e}`)
  }
}

capture().catch((err) => {
  console.error(err)
  process.exit(1)
})
