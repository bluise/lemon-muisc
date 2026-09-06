import { randomUUID } from 'node:crypto'
import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'
import { getGlobalSettings, setGlobalSettings } from './userSettings.js'
import { getOnlineClientCount } from '../ws.js'

const INTERVAL_MS = 10 * 60 * 1000
const KEY_INSTALL = 'telemetry.installId'
const KEY_ENABLED = 'telemetry.enabled'
const KEY_DAY = 'telemetry.day'
const KEY_MINUTES = 'telemetry.activeMinutes'

const __dirname = path.dirname(fileURLToPath(import.meta.url))

let timer = null
let tickInFlight = false
let cachedEndpoint = null

function decodeMaybeB64(plain, b64) {
  if (plain && String(plain).trim()) return String(plain).trim()
  if (b64 && String(b64).trim()) {
    try {
      return Buffer.from(String(b64).trim(), 'base64').toString('utf8').trim()
    } catch {
      return ''
    }
  }
  return ''
}

function readLocalTelemetryFile() {
  const candidates = []
  if (process.env.CONFIG_PATH) {
    candidates.push(path.join(process.env.CONFIG_PATH, 'telemetry.local.json'))
  }
  candidates.push(path.join(__dirname, '..', 'telemetry.local.json'))
  candidates.push(path.join(__dirname, '..', '..', 'config', 'telemetry.local.json'))

  for (const file of candidates) {
    try {
      if (!fs.existsSync(file)) continue
      const raw = JSON.parse(fs.readFileSync(file, 'utf8'))
      if (raw && typeof raw === 'object') return raw
    } catch {
      // ignore bad file
    }
  }
  return null
}

/** Resolve endpoint from env / local file (never hardcode public domain in repo).
 * Priority: TELEMETRY_URL + TELEMETRY_SECRET → telemetry.local.json (url/secret or *B64).
 * Git commits must not contain real endpoints; FPK release packs inject locally via scripts/build-fpk.ps1.
 */
export function getTelemetryEndpoint() {
  if (cachedEndpoint) return cachedEndpoint

  const file = readLocalTelemetryFile()
  const url = decodeMaybeB64(
    process.env.TELEMETRY_URL || file?.url,
    file?.urlB64,
  )
  const secret = decodeMaybeB64(
    process.env.TELEMETRY_SECRET || file?.secret,
    file?.secretB64,
  )

  cachedEndpoint = {
    url: url || '',
    secret: secret || '',
    configured: Boolean(url && secret),
  }
  return cachedEndpoint
}

function readAppVersion() {
  try {
    const pkgPath = path.join(__dirname, '..', '..', 'package.json')
    const pkg = JSON.parse(fs.readFileSync(pkgPath, 'utf8'))
    return String(pkg.version || '0.0.0')
  } catch {
    return '0.0.0'
  }
}

function localDayKey(date = new Date()) {
  const y = date.getFullYear()
  const m = String(date.getMonth() + 1).padStart(2, '0')
  const d = String(date.getDate()).padStart(2, '0')
  return `${y}-${m}-${d}`
}

export function isTelemetryEnabled() {
  const s = getGlobalSettings()
  return s[KEY_ENABLED] !== 'false'
}

export function ensureInstallId() {
  const s = getGlobalSettings()
  if (s[KEY_INSTALL]) return s[KEY_INSTALL]
  const id = randomUUID()
  setGlobalSettings({ [KEY_INSTALL]: id })
  return id
}

function readDayState() {
  const s = getGlobalSettings()
  const day = localDayKey()
  if (s[KEY_DAY] !== day) {
    setGlobalSettings({
      [KEY_DAY]: day,
      [KEY_MINUTES]: '0',
    })
    return { day, activeMinutes: 0 }
  }
  const minutes = Math.max(0, Number.parseInt(s[KEY_MINUTES] || '0', 10) || 0)
  return { day, activeMinutes: minutes }
}

function writeActiveMinutes(day, activeMinutes) {
  setGlobalSettings({
    [KEY_DAY]: day,
    [KEY_MINUTES]: String(Math.max(0, Math.floor(activeMinutes))),
  })
}

async function postHeartbeat(payload, endpoint) {
  const controller = new AbortController()
  const timeout = setTimeout(() => controller.abort(), 15000)
  try {
    const res = await fetch(endpoint.url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Lemon-Telemetry-Key': endpoint.secret,
      },
      body: JSON.stringify(payload),
      signal: controller.signal,
    })
    if (!res.ok) {
      const text = await res.text().catch(() => '')
      console.warn(`[telemetry] report failed: HTTP ${res.status} ${text.slice(0, 120)}`)
    }
  } catch (err) {
    console.warn(`[telemetry] report error: ${err?.message || err}`)
  } finally {
    clearTimeout(timeout)
  }
}

async function tick() {
  if (tickInFlight) return
  if (!isTelemetryEnabled()) return

  const endpoint = getTelemetryEndpoint()
  if (!endpoint.configured) return

  tickInFlight = true
  try {
    const installId = ensureInstallId()
    const onlineSessions = getOnlineClientCount()
    let { day, activeMinutes } = readDayState()

    if (onlineSessions > 0) {
      activeMinutes += 10
      writeActiveMinutes(day, activeMinutes)
    }

    if (activeMinutes <= 0 && onlineSessions <= 0) return

    await postHeartbeat({
      installId,
      app: 'lemon-music',
      version: readAppVersion(),
      day,
      activeMinutes,
      onlineSessions,
    }, endpoint)
  } finally {
    tickInFlight = false
  }
}

export function startTelemetry() {
  if (timer) return
  try {
    ensureInstallId()
  } catch (err) {
    console.warn(`[telemetry] init failed: ${err?.message || err}`)
    return
  }

  const endpoint = getTelemetryEndpoint()
  if (!endpoint.configured) {
    console.log('[telemetry] inactive (no TELEMETRY_URL/SECRET or telemetry.local.json)')
    return
  }

  setTimeout(() => {
    tick().catch(() => {})
  }, 30_000).unref?.()
  timer = setInterval(() => {
    tick().catch(() => {})
  }, INTERVAL_MS)
  timer.unref?.()
  console.log('[telemetry] active (endpoint configured)')
}

export function stopTelemetry() {
  if (timer) {
    clearInterval(timer)
    timer = null
  }
}
