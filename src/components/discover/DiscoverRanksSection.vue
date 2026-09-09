<template>
  <section class="discover-section">
    <header class="section-head">
      <h2 class="section-title">排行榜</h2>
      <button type="button" class="section-more" @click="$emit('more')">更多 &gt;</button>
    </header>

    <div v-if="loading && !list.length" class="section-skeleton">
      <DiscoverSectionSkeleton variant="ranks" />
    </div>
    <div v-else-if="unsupported" class="section-state">当前平台暂不支持排行榜</div>
    <div v-else-if="error && !list.length" class="section-state error">{{ error }}</div>
    <div v-else-if="!list.length" class="section-state">暂无排行榜</div>
    <div v-else class="rank-list">
      <button
        v-for="(item, idx) in visibleList"
        :key="`${item.source}-${item.id}`"
        type="button"
        class="rank-card"
        @click="$emit('open', item)"
      >
        <div class="rank-cover" :style="coverTone(item, idx)">
          <CoverArt v-if="item.cover" :src="item.cover" />
          <span v-else class="cover-fallback">{{ nameInitial(item) }}</span>
        </div>
        <div class="rank-body">
          <div class="rank-name" :title="cleanText(item.name)">{{ cleanText(item.name) }}</div>
          <ol class="rank-songs">
            <li v-for="(song, i) in previewSongs(item)" :key="i">
              <span class="idx">{{ i + 1 }}</span>
              <span class="song-line">
                <span class="s-name">{{ song.name }}</span>
                <template v-if="song.singer">
                  <span class="sep">/</span>
                  <span class="s-singer">{{ song.singer }}</span>
                </template>
              </span>
            </li>
            <li v-if="!previewSongs(item).length" class="rank-empty">点击查看榜单</li>
          </ol>
        </div>
      </button>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue'
import CoverArt from '../CoverArt.vue'
import DiscoverSectionSkeleton from './DiscoverSectionSkeleton.vue'
import { cleanText } from '../../utils/text.js'

const props = defineProps({
  list: { type: Array, default: () => [] },
  loading: Boolean,
  error: { type: String, default: '' },
  unsupported: Boolean,
  limit: { type: Number, default: 8 },
})
defineEmits(['open', 'more'])

const visibleList = computed(() => props.list.slice(0, props.limit))

function previewSongs(item) {
  return (item?.songs || [])
    .map((s) => {
      if (!s || typeof s !== 'object') return null
      let name = cleanText(s.name || s.title || '')
      let singer = cleanText(s.singer || s.author || s.artist || '')
      const combined = cleanText(s.songname || s.filename || '')
      if ((!name || !singer) && combined) {
        const parts = combined.split(/\s*-\s*/)
        if (parts.length > 1) {
          if (!singer) singer = parts[0]
          if (!name) name = parts.slice(1).join(' - ')
        } else if (!name) {
          name = combined
        }
      }
      if (!name && !singer) return null
      return { name: name || '未知歌曲', singer }
    })
    .filter(Boolean)
    .slice(0, 3)
}

const PALETTE = [
  '#c45c7a',
  '#5a6f82',
  '#3d8f7c',
  '#6a5a94',
  '#b07840',
  '#3d6a9e',
  '#8b3a55',
  '#2f5e5a',
]

function coverTone(item, idx) {
  const color = item.color || PALETTE[idx % PALETTE.length]
  return { '--rank-tone': color }
}

function nameInitial(item) {
  const name = cleanText(item?.name || '')
  return name.slice(0, 1) || '榜'
}
</script>

<style scoped>
.discover-section { margin-top: 28px; margin-bottom: 12px; }
.section-head {
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
  margin-bottom: 14px;
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
.section-state {
  text-align: center;
  color: var(--text-muted);
  padding: 24px 8px;
  font-size: 13px;
}
.section-state.error { color: var(--error); }

.rank-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.rank-card {
  display: grid;
  grid-template-columns: 72px minmax(0, 1fr);
  gap: 12px;
  align-items: stretch;
  width: 100%;
  margin: 0;
  padding: 10px;
  border: 1px solid var(--border-light);
  border-radius: 12px;
  background: var(--bg-elevated, var(--bg-card));
  color: inherit;
  text-align: left;
  cursor: pointer;
  transition: background 0.15s ease, border-color 0.15s ease;
}
.rank-card:hover {
  background: var(--bg-hover);
  border-color: var(--border);
}

.rank-cover {
  position: relative;
  width: 72px;
  height: 72px;
  border-radius: 10px;
  overflow: hidden;
  flex-shrink: 0;
  background: color-mix(in srgb, var(--rank-tone, var(--accent)) 75%, #111);
}
.rank-cover :deep(.cover-art) {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.rank-cover::after {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(
    160deg,
    color-mix(in srgb, var(--rank-tone, transparent) 35%, transparent),
    transparent 70%
  );
  pointer-events: none;
}
.cover-fallback {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 100%;
  color: #fff;
  font-size: 22px;
  font-weight: 700;
}

.rank-body {
  min-width: 0;
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: 6px;
}
.rank-name {
  font-size: 15px;
  font-weight: 650;
  line-height: 1.25;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.rank-songs {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 3px;
}
.rank-songs li {
  display: grid;
  grid-template-columns: 14px minmax(0, 1fr);
  gap: 6px;
  align-items: baseline;
  min-width: 0;
}
.idx {
  font-size: 11px;
  color: var(--text-muted);
  font-variant-numeric: tabular-nums;
}
.song-line {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: 12px;
  line-height: 1.35;
  color: var(--text-secondary, var(--text-muted));
}
.s-name { color: var(--text-secondary, var(--text-muted)); }
.sep {
  margin: 0 4px;
  opacity: 0.55;
}
.s-singer { opacity: 0.85; }
.rank-empty {
  font-size: 12px;
  color: var(--text-muted);
}

@media (min-width: 720px) {
  .rank-list {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
  }
  .rank-card {
    grid-template-columns: 88px minmax(0, 1fr);
    padding: 12px;
  }
  .rank-cover {
    width: 88px;
    height: 88px;
  }
}

@media (min-width: 1100px) {
  .rank-list {
    grid-template-columns: repeat(4, minmax(0, 1fr));
  }
}

@media (max-width: 560px) {
  .section-title { font-size: 18px; }
  .rank-card {
    grid-template-columns: 64px minmax(0, 1fr);
    gap: 10px;
    padding: 8px;
    border-radius: 10px;
  }
  .rank-cover {
    width: 64px;
    height: 64px;
    border-radius: 8px;
  }
  .rank-name { font-size: 14px; }
  .song-line { font-size: 11px; }
}
</style>
