<template>
  <section class="discover-section">
    <header class="section-head">
      <h2 class="section-title">新碟首发</h2>
      <button type="button" class="section-more" @click="$emit('more')">更多 &gt;</button>
    </header>
    <div v-if="regions.length" class="section-tabs">
      <button
        v-for="r in regions"
        :key="r.id"
        type="button"
        class="chip"
        :class="{ active: region === r.id }"
        @click="$emit('update:region', r.id)"
      >{{ r.label }}</button>
    </div>

    <div v-if="loading && !list.length" class="section-skeleton">
      <DiscoverSectionSkeleton variant="albums" />
    </div>
    <div v-else-if="unsupported" class="section-state">当前平台暂不支持新碟首发</div>
    <div v-else-if="error && !list.length" class="section-state error">{{ error }}</div>
    <div v-else-if="!list.length" class="section-state">暂无新碟</div>
    <DiscoverSwipeCarousel
      v-else
      v-model="pageIndex"
      :page-count="pageCount"
    >
      <template #default="{ page }">
        <div class="album-grid">
          <button
            v-for="item in itemsForPage(page)"
            :key="`${item.source}-${item.id}`"
            type="button"
            class="album-card"
            @click="$emit('open', item)"
          >
            <div class="cover-wrap">
              <CoverArt :src="item.img" />
              <span class="play-overlay" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="28" height="28" fill="#fff"><polygon points="8,5 19,12 8,19"/></svg>
              </span>
            </div>
            <div class="name" :title="cleanText(item.name)">{{ cleanText(item.name) }}</div>
            <div class="artist" :title="cleanText(item.artist)">{{ cleanText(item.artist) || '未知艺人' }}</div>
          </button>
        </div>
      </template>
    </DiscoverSwipeCarousel>
    <DiscoverSectionPager v-model="pageIndex" :page-count="pageCount" label="新碟分页" />
  </section>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import CoverArt from '../CoverArt.vue'
import DiscoverSectionPager from './DiscoverSectionPager.vue'
import DiscoverSectionSkeleton from './DiscoverSectionSkeleton.vue'
import DiscoverSwipeCarousel from './DiscoverSwipeCarousel.vue'
import { cleanText } from '../../utils/text.js'

const props = defineProps({
  list: { type: Array, default: () => [] },
  loading: Boolean,
  error: { type: String, default: '' },
  unsupported: Boolean,
  region: { type: String, default: '' },
  regions: { type: Array, default: () => [] },
  pageSize: { type: Number, default: 10 },
})
defineEmits(['open', 'more', 'update:region'])

const pageIndex = ref(0)
const pageCount = computed(() => Math.max(1, Math.ceil(props.list.length / props.pageSize)))

function itemsForPage(page) {
  const start = page * props.pageSize
  return props.list.slice(start, start + props.pageSize)
}

watch(() => [props.list, props.region], () => { pageIndex.value = 0 })
watch(pageCount, (n) => {
  if (pageIndex.value >= n) pageIndex.value = Math.max(0, n - 1)
})
</script>

<style scoped>
.discover-section { margin-top: 28px; }
.section-head {
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
  margin-bottom: 10px;
}
.section-title { margin: 0; font-size: 22px; font-weight: 700; }
.section-more {
  position: absolute;
  right: 0;
  border: none;
  background: transparent;
  color: var(--text-muted);
  font-size: 13px;
  cursor: pointer;
}
.section-more:hover { color: var(--accent); }
.section-tabs {
  display: flex;
  justify-content: center;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 14px;
}
.chip {
  border: none;
  background: transparent;
  color: var(--text-muted);
  font-size: 13px;
  padding: 4px 10px;
  border-radius: 999px;
  cursor: pointer;
}
.chip.active {
  color: var(--accent);
  font-weight: 600;
  background: var(--accent-muted);
}
.section-state {
  text-align: center;
  color: var(--text-muted);
  padding: 24px 8px;
  font-size: 13px;
}
.section-state.error { color: var(--error); }
.album-grid {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 16px 12px;
}
.album-card {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 0;
  border: none;
  background: transparent;
  text-align: left;
  cursor: pointer;
  min-width: 0;
}
.cover-wrap {
  position: relative;
  aspect-ratio: 1;
  border-radius: 8px;
  overflow: hidden;
  background: var(--bg-input);
}
.cover-wrap :deep(.cover-art) { width: 100%; height: 100%; }
.play-overlay {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(0, 0, 0, 0.42);
  opacity: 0;
  transition: opacity 0.18s ease;
}
.album-card:hover .play-overlay { opacity: 1; }
.name, .artist {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.name { font-size: 13px; font-weight: 500; }
.artist { font-size: 12px; color: var(--text-muted); }

@media (max-width: 960px) {
  .album-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
}
@media (max-width: 720px) {
  .album-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
  .section-title { font-size: 18px; }
  .play-overlay { opacity: 0.85; background: rgba(0,0,0,0.28); }
  .play-overlay svg { width: 22px; height: 22px; }
}
@media (max-width: 480px) {
  .album-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
</style>
