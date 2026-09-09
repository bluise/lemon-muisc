<template>
  <div class="discover-more-page">
    <div class="more-toolbar">
      <button type="button" class="btn-ghost btn-sm" @click="router.push('/discover')">← 返回发现</button>
      <h1 class="more-title">新歌首发</h1>
    </div>

    <div class="more-filters card">
      <div class="source-tabs">
        <button
          v-for="(info, key) in sources"
          :key="key"
          type="button"
          class="tab"
          :class="{ active: source === key }"
          @click="onSourceChange(key)"
        >{{ platformLabel(key, info) }}</button>
      </div>
      <div v-if="regions.length" class="chip-row">
        <button
          v-for="r in regions"
          :key="r.id"
          type="button"
          class="chip"
          :class="{ active: region === r.id }"
          @click="changeRegion(r.id)"
        >{{ r.label }}</button>
      </div>
      <button type="button" class="btn-primary btn-sm play-all" :disabled="!list.length" @click="playAll">
        播放全部
      </button>
    </div>

    <div v-if="loading && !list.length" class="state">加载中...</div>
    <div v-else-if="unsupported" class="state">当前平台暂不支持</div>
    <div v-else-if="error && !list.length" class="state error">{{ error }}</div>
    <div v-else class="song-list card">
      <div class="song-grid">
        <DiscoverSongItem
          v-for="(item, i) in list"
          :key="`${item.songmid || item.hash || item.id}-${i}`"
          :item="item"
          :index="startIndex + i"
          :playing="isPlayingItem(item)"
          :paused="isPaused"
          :loading="loadingPlay === item.id"
          :eager-cover="i < 24"
          @play="playOne(item)"
        >
          <template #actions>
            <DiscoverSongActions
              :item="item"
              :source="item.source || source"
              @toast="onToast"
            />
          </template>
        </DiscoverSongItem>
      </div>
    </div>

    <div v-if="list.length || totalPages > 1" class="pager">
      <button
        type="button"
        class="btn-ghost btn-sm"
        :disabled="page <= 1 || loading"
        @click="goPage(page - 1)"
      >上一页</button>
      <span class="page-info">第 {{ page }} / {{ totalPages }} 页</span>
      <button
        type="button"
        class="btn-ghost btn-sm"
        :disabled="!canNext || loading"
        @click="goPage(page + 1)"
      >下一页</button>
    </div>

    <div v-if="toast" class="toast" :class="toast.type">{{ toast.text }}</div>
  </div>
</template>

<script setup>
defineOptions({ name: 'DiscoverNewSongs' })
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import DiscoverSongItem from '../components/discover/DiscoverSongItem.vue'
import DiscoverSongActions from '../components/discover/DiscoverSongActions.vue'
import { useProgressiveTrackCovers } from '../composables/useProgressiveTrackCovers.js'
import { api } from '../api.js'
import { discoverState, loadDiscoverSources, defaultSongRegion } from '../stores/discover.js'
import { playItem, addToQueue, isPlayingItem, isPaused, loadingPlay } from '../stores/player.js'
import { platformLabel } from '../utils/platforms.js'

const PAGE_SIZE = 30

const route = useRoute()
const router = useRouter()
const sources = ref({})
const source = ref(String(route.query.source || discoverState.activeSource || 'tx'))
const region = ref(String(route.query.region || defaultSongRegion(source.value) || ''))
const regions = ref([])
const list = ref([])
const page = ref(1)
const allPage = ref(1)
const hasMore = ref(false)
const loading = ref(false)
const error = ref('')
const unsupported = ref(false)
const toast = ref(null)
let toastTimer = 0

function onToast({ text, type }) {
  toast.value = { text, type: type || 'info' }
  clearTimeout(toastTimer)
  toastTimer = window.setTimeout(() => { toast.value = null }, 2800)
}

const totalPages = computed(() => {
  if (allPage.value > 1) return allPage.value
  if (hasMore.value) return page.value + 1
  return Math.max(1, page.value)
})

const canNext = computed(() => {
  if (allPage.value > 1) return page.value < allPage.value
  return hasMore.value
})

const startIndex = computed(() => (page.value - 1) * PAGE_SIZE + 1)

useProgressiveTrackCovers(() => list.value, {
  getSource: () => source.value,
})

async function playOne(item) {
  try {
    await playItem(item, item.source || source.value)
  } catch (e) {
    error.value = e.message || '播放失败'
  }
}

async function playAll() {
  if (!list.value.length) return
  for (const item of list.value.slice(0, 100)) addToQueue(item, item.source || source.value)
  try {
    await playItem(list.value[0], list.value[0].source || source.value)
  } catch (e) {
    error.value = e.message || '播放失败'
  }
}

async function onSourceChange(key) {
  if (source.value === key) return
  source.value = key
  region.value = defaultSongRegion(key)
  page.value = 1
  list.value = []
  await load()
}

async function changeRegion(id) {
  if (region.value === id) return
  region.value = id
  page.value = 1
  list.value = []
  await load()
}

async function goPage(next) {
  if (next < 1 || loading.value) return
  if (next > page.value && !canNext.value) return
  page.value = next
  await load()
}

async function load() {
  loading.value = true
  error.value = ''
  unsupported.value = false
  try {
    const res = await api.discover.newSongs(source.value, region.value, page.value, PAGE_SIZE)
    const data = res.data || {}
    unsupported.value = Boolean(data.unsupported)
    regions.value = data.regions || []
    if (data.region != null && data.region !== '') region.value = String(data.region)
    const batch = data.list || []
    list.value = batch
    allPage.value = Math.max(1, Number(data.allPage) || 1)
    if (data.allPage) {
      hasMore.value = page.value < allPage.value
    } else {
      hasMore.value = batch.length >= PAGE_SIZE
      if (hasMore.value) allPage.value = page.value + 1
      else allPage.value = Math.max(1, page.value)
    }
    if (!batch.length && page.value > 1) {
      hasMore.value = false
      allPage.value = page.value - 1
      page.value -= 1
      await load()
    }
  } catch (e) {
    error.value = e.message || '加载失败'
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  await loadDiscoverSources(api)
  sources.value = discoverState.sources
  if (!sources.value[source.value]) {
    source.value = Object.keys(sources.value)[0] || source.value
    region.value = defaultSongRegion(source.value)
  }
  await load()
})
</script>

<style scoped>
.discover-more-page { padding: 8px 4px 40px; }
.more-toolbar {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 14px;
}
.more-title { margin: 0; font-size: 20px; font-weight: 700; }
.more-filters {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  align-items: center;
  padding: 12px 14px;
  margin-bottom: 16px;
}
.source-tabs {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}
.tab {
  padding: 5px 14px;
  border-radius: var(--radius-pill);
  background: transparent;
  color: var(--text-secondary);
  font-size: 13px;
  border: 1px solid var(--border);
  cursor: pointer;
}
.tab:hover { background: var(--bg-hover); color: var(--text); }
.tab.active {
  color: #fff;
  background: var(--accent);
  border-color: var(--accent);
}
.chip-row { display: flex; flex-wrap: wrap; gap: 8px; }
.chip {
  border: none;
  background: transparent;
  color: var(--text-muted);
  padding: 4px 12px;
  border-radius: 999px;
  cursor: pointer;
}
.chip.active { color: var(--accent); background: var(--accent-muted); font-weight: 600; }
.play-all { margin-left: auto; }
.state { text-align: center; padding: 40px 8px; color: var(--text-muted); }
.state.error { color: var(--error); }
.song-list { padding: 8px; }
.song-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 4px 12px;
}
.song-list :deep(.discover-song-item) {
  border-radius: 10px;
}
@media (max-width: 960px) {
  .song-list :deep(.song-time) { display: none; }
}
.pager {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 12px;
  margin-top: 20px;
}
.page-info { font-size: 13px; color: var(--text-muted); }

.toast {
  position: fixed;
  bottom: 80px;
  right: 24px;
  padding: 10px 20px;
  border-radius: var(--radius);
  font-size: 14px;
  z-index: 1000;
  box-shadow: var(--shadow);
}
.toast.success { background: var(--success); color: #fff; }
.toast.error { background: var(--error); color: #fff; }
.toast.info { background: var(--bg-card); border: 1px solid var(--border); color: var(--text); }

@media (max-width: 640px) {
  .song-grid {
    grid-template-columns: 1fr;
    gap: 2px;
  }
  .play-all { margin-left: 0; width: 100%; }
}
</style>
