<template>
  <div class="discover-more-page">
    <div class="more-toolbar">
      <button type="button" class="btn-ghost btn-sm" @click="router.push('/discover')">← 返回发现</button>
      <h1 class="more-title">新碟首发</h1>
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
    </div>

    <div v-if="loading && !list.length" class="state">加载中...</div>
    <div v-else-if="unsupported" class="state">当前平台暂不支持</div>
    <div v-else-if="error && !list.length" class="state error">{{ error }}</div>
    <div v-else class="grid">
      <button
        v-for="item in list"
        :key="`${item.source}-${item.id}`"
        type="button"
        class="album-card"
        @click="openAlbum(item)"
      >
        <div class="cover"><CoverArt :src="item.img" /></div>
        <div class="name">{{ cleanText(item.name) }}</div>
        <div class="sub">{{ cleanText(item.artist) }}</div>
      </button>
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
  </div>
</template>

<script setup>
defineOptions({ name: 'DiscoverNewAlbums' })
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import CoverArt from '../components/CoverArt.vue'
import { api } from '../api.js'
import { discoverState, loadDiscoverSources, defaultAlbumRegion } from '../stores/discover.js'
import { platformLabel } from '../utils/platforms.js'
import { cleanText } from '../utils/text.js'

const PAGE_SIZE = 30

const route = useRoute()
const router = useRouter()
const sources = ref({})
const source = ref(String(route.query.source || discoverState.activeSource || 'tx'))
const region = ref(String(route.query.region || defaultAlbumRegion(source.value) || ''))
const regions = ref([])
const list = ref([])
const page = ref(1)
const total = ref(0)
const allPage = ref(1)
const hasMore = ref(false)
const loading = ref(false)
const error = ref('')
const unsupported = ref(false)

const totalPages = computed(() => {
  if (total.value > 0) return Math.max(1, Math.ceil(total.value / PAGE_SIZE))
  if (allPage.value > 1) return allPage.value
  if (hasMore.value) return page.value + 1
  return Math.max(1, page.value)
})

const canNext = computed(() => {
  if (total.value > 0) return page.value < totalPages.value
  if (allPage.value > 1) return page.value < allPage.value
  return hasMore.value
})

function openAlbum(item) {
  router.push({
    path: '/discover',
    query: {
      openAlbum: item.id,
      source: item.source || source.value,
      albumName: item.name || '',
      albumArtist: item.artist || '',
      albumImg: item.img || '',
    },
  })
}

async function onSourceChange(key) {
  if (source.value === key) return
  source.value = key
  region.value = defaultAlbumRegion(key)
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
    const res = await api.discover.newAlbums(source.value, region.value, page.value, PAGE_SIZE)
    const data = res.data || {}
    const chunk = data.list || []
    list.value = chunk
    regions.value = data.regions || []
    unsupported.value = Boolean(data.unsupported)
    total.value = Number(data.total) || 0
    allPage.value = Math.max(1, Number(data.allPage) || 1)
    if (unsupported.value) {
      hasMore.value = false
    } else if (total.value > 0) {
      hasMore.value = page.value * PAGE_SIZE < total.value
    } else if (data.allPage) {
      hasMore.value = page.value < allPage.value
    } else {
      hasMore.value = chunk.length >= PAGE_SIZE
      allPage.value = hasMore.value ? page.value + 1 : Math.max(1, page.value)
    }
    if (!chunk.length && page.value > 1 && !unsupported.value) {
      hasMore.value = false
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
    region.value = defaultAlbumRegion(source.value)
  }
  await load()
})
</script>

<style scoped>
.discover-more-page { padding-bottom: 24px; }
.more-toolbar { display: flex; align-items: center; gap: 12px; margin-bottom: 14px; }
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
.state { text-align: center; padding: 40px 8px; color: var(--text-muted); }
.state.error { color: var(--error); }
.grid {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 16px 12px;
}
.album-card {
  border: none;
  background: transparent;
  text-align: left;
  cursor: pointer;
  min-width: 0;
}
.cover {
  aspect-ratio: 1;
  border-radius: 8px;
  overflow: hidden;
  background: var(--bg-input);
  margin-bottom: 8px;
}
.cover :deep(.cover-art) { width: 100%; height: 100%; }
.name, .sub { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.name { font-size: 13px; font-weight: 500; }
.sub { font-size: 12px; color: var(--text-muted); }
.pager {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 12px;
  margin-top: 20px;
}
.page-info { font-size: 13px; color: var(--text-muted); }
@media (max-width: 960px) { .grid { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
@media (max-width: 720px) { .grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
@media (max-width: 480px) { .grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
</style>
