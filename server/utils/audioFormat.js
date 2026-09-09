import fs from 'fs'

/** 需要真实无损容器的音质（禁止用 MP3 冒充） */
export function isLosslessQuality(quality) {
  const q = String(quality || '').toLowerCase()
  return q.includes('flac')
    || q.includes('hires')
    || q.includes('master')
    || q.includes('atmos')
}

export function createFakeLosslessError(detail = '') {
  const hint = detail ? `（${String(detail).slice(0, 120)}）` : ''
  const err = new Error(`音源返回了假无损文件${hint}，已拒绝保存`)
  err.code = 'FAKE_LOSSLESS'
  return err
}

/**
 * 读取文件头判断容器类型（不解析完整元数据）
 * @returns {'flac'|'mp3'|'ogg'|'wav'|'m4a'|'unknown'}
 */
export function detectAudioContainer(filePath) {
  if (!filePath || !fs.existsSync(filePath)) return 'unknown'
  const buf = Buffer.alloc(32)
  let n = 0
  try {
    const fd = fs.openSync(filePath, 'r')
    try {
      n = fs.readSync(fd, buf, 0, buf.length, 0)
    } finally {
      fs.closeSync(fd)
    }
  } catch {
    return 'unknown'
  }
  if (n < 4) return 'unknown'

  if (buf.slice(0, 4).toString('ascii') === 'fLaC') return 'flac'
  if (buf.slice(0, 4).toString('ascii') === 'OggS') return 'ogg'
  if (buf.slice(0, 4).toString('ascii') === 'RIFF' && n >= 12 && buf.slice(8, 12).toString('ascii') === 'WAVE') {
    return 'wav'
  }
  if (buf.slice(0, 4).toString('ascii') === 'MAC ') return 'ape'
  if (buf.slice(0, 3).toString('ascii') === 'ID3') return 'mp3'
  // MPEG frame sync
  if (buf[0] === 0xff && (buf[1] & 0xe0) === 0xe0) return 'mp3'
  // ISO BMFF (m4a/mp4): ....ftyp
  if (n >= 8 && buf.slice(4, 8).toString('ascii') === 'ftyp') return 'm4a'
  return 'unknown'
}

/**
 * 无损音质落盘后校验：必须是真实 FLAC（或明显的无损容器）。
 * QQ 等音源偶发返回「假 flac」（实为 MP3）却带 .flac 后缀。
 */
export function assertLosslessFile(filePath, quality) {
  if (!isLosslessQuality(quality)) return

  const kind = detectAudioContainer(filePath)
  if (kind === 'flac') {
    // 时长较长却极小：常见假文件/截断（可选加固）
    try {
      const size = fs.statSync(filePath).size
      if (size > 0 && size < 256 * 1024) {
        throw createFakeLosslessError(`文件过小 ${Math.round(size / 1024)}KB，不像完整 FLAC`)
      }
    } catch (e) {
      if (e?.code === 'FAKE_LOSSLESS') throw e
    }
    return
  }

  if (kind === 'mp3') {
    throw createFakeLosslessError('文件头为 MP3/ID3，不是 FLAC')
  }
  if (kind === 'm4a') {
    throw createFakeLosslessError('文件头为 M4A/MP4，不是 FLAC')
  }
  if (kind === 'ogg' || kind === 'wav') {
    // 少数音源用其它无损封装；若用户点的是 flac 档，仍视为不符
    throw createFakeLosslessError(`文件头为 ${kind.toUpperCase()}，不是 FLAC`)
  }
  throw createFakeLosslessError('无法识别为有效 FLAC')
}

/** 响应头提前拦截明显的有损 Content-Type */
export function assertLosslessContentType(contentType, quality) {
  if (!isLosslessQuality(quality)) return
  const ct = String(contentType || '').toLowerCase()
  if (!ct) return
  if (/flac|ogg|wav|x-flac/.test(ct)) return
  if (/mpeg|mp3|aac|mp4|m4a/.test(ct)) {
    throw createFakeLosslessError(`Content-Type=${ct.split(';')[0]}`)
  }
}
