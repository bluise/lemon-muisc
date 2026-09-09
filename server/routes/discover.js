import { Router } from 'express'
import {
  fetchNewSongs,
  fetchNewAlbums,
  fetchToplists,
  fetchToplistDetail,
  getDiscoverRegions,
} from '../discoverSdk.js'
import { getDisplaySources } from '../utils/displaySources.js'
import { formatUserError } from '../utils/userError.js'
import { createLimiter, withTimeout } from '../utils/asyncLimit.js'

export const discoverRouter = Router()

const availableSources = (req) => getDisplaySources(req.user?.id)
const discoverLimiter = createLimiter(4)
const TIMEOUT_MS = 45000

function guardSource(req, source) {
  if (!availableSources(req)[source]) {
    const err = new Error(`不支持的平台: ${source}`)
    err.status = 400
    throw err
  }
}

discoverRouter.get('/regions', (req, res) => {
  const { source = 'tx', kind = 'songs' } = req.query
  if (!availableSources(req)[source]) {
    return res.status(400).json({ error: `不支持的平台: ${source}` })
  }
  res.json({ ok: true, regions: getDiscoverRegions(String(source), String(kind)) })
})

discoverRouter.get('/new-songs', async (req, res) => {
  try {
    const { source = 'tx', region = '', page = 1, limit = 27 } = req.query
    guardSource(req, source)
    const data = await discoverLimiter(() => withTimeout(
      fetchNewSongs(String(source), String(region), Number(page) || 1, Number(limit) || 27),
      TIMEOUT_MS,
      '获取新歌超时，请稍后重试',
    ))
    res.json({ ok: true, data })
  } catch (e) {
    res.status(e.status || 500).json({ error: formatUserError(e, '获取新歌失败，请稍后重试') })
  }
})

discoverRouter.get('/new-albums', async (req, res) => {
  try {
    const { source = 'tx', region = '', page = 1, limit = 20 } = req.query
    guardSource(req, source)
    const data = await discoverLimiter(() => withTimeout(
      fetchNewAlbums(String(source), String(region), Number(page) || 1, Number(limit) || 20),
      TIMEOUT_MS,
      '获取新碟超时，请稍后重试',
    ))
    res.json({ ok: true, data })
  } catch (e) {
    res.status(e.status || 500).json({ error: formatUserError(e, '获取新碟失败，请稍后重试') })
  }
})

discoverRouter.get('/toplists', async (req, res) => {
  try {
    const { source = 'tx' } = req.query
    guardSource(req, source)
    const data = await discoverLimiter(() => withTimeout(
      fetchToplists(String(source)),
      TIMEOUT_MS,
      '获取排行榜超时，请稍后重试',
    ))
    res.json({ ok: true, data })
  } catch (e) {
    res.status(e.status || 500).json({ error: formatUserError(e, '获取排行榜失败，请稍后重试') })
  }
})

discoverRouter.get('/toplist', async (req, res) => {
  try {
    const { source = 'tx', id = '', page = 1, limit = 100 } = req.query
    guardSource(req, source)
    if (!id) return res.status(400).json({ error: '缺少榜单 ID' })
    const data = await discoverLimiter(() => withTimeout(
      fetchToplistDetail(String(source), String(id), Number(page) || 1, Number(limit) || 100),
      TIMEOUT_MS,
      '获取榜单详情超时，请稍后重试',
    ))
    res.json({ ok: true, data })
  } catch (e) {
    res.status(e.status || 500).json({ error: formatUserError(e, '获取榜单详情失败，请稍后重试') })
  }
})
