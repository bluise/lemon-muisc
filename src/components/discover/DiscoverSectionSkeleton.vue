<template>
  <div class="discover-sk" :class="`discover-sk--${variant}`" aria-hidden="true">
    <template v-if="variant === 'playlists'">
      <div class="sk-grid playlists">
        <div v-for="i in playlistCount" :key="i" class="sk-cell">
          <div class="sk-cover" />
          <div class="sk-line" />
          <div class="sk-line short" />
        </div>
      </div>
    </template>

    <template v-else-if="variant === 'songs'">
      <div class="sk-song-grid">
        <div v-for="i in 6" :key="i" class="sk-song-row">
          <div class="sk-cover sm" />
          <div class="sk-song-meta">
            <div class="sk-line" />
            <div class="sk-line short" />
          </div>
        </div>
      </div>
    </template>

    <template v-else-if="variant === 'albums'">
      <div class="sk-grid albums">
        <div v-for="i in albumCount" :key="i" class="sk-cell">
          <div class="sk-cover" />
          <div class="sk-line" />
          <div class="sk-line short" />
        </div>
      </div>
    </template>

    <template v-else-if="variant === 'ranks'">
      <div class="sk-rank-list">
        <div v-for="i in 4" :key="i" class="sk-rank-row">
          <div class="sk-cover rank" />
          <div class="sk-rank-meta">
            <div class="sk-line" />
            <div class="sk-line short" />
            <div class="sk-line short" />
          </div>
        </div>
      </div>
    </template>

    <template v-else>
      <div class="sk-block" />
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'

const props = defineProps({
  variant: {
    type: String,
    default: 'playlists', // playlists | songs | albums | ranks
  },
})

const width = ref(typeof window !== 'undefined' ? window.innerWidth : 1200)
function onResize() {
  width.value = window.innerWidth
}
onMounted(() => window.addEventListener('resize', onResize))
onUnmounted(() => window.removeEventListener('resize', onResize))

const playlistCount = computed(() => {
  if (width.value <= 480) return 4
  if (width.value <= 720) return 6
  if (width.value <= 960) return 8
  return 10
})

const albumCount = computed(() => {
  if (width.value <= 480) return 4
  if (width.value <= 720) return 6
  return 10
})
</script>

<style scoped>
.discover-sk {
  animation: sk-fade-in 0.15s ease;
}

@keyframes sk-fade-in {
  from { opacity: 0; }
  to { opacity: 1; }
}

.sk-cover,
.sk-line,
.sk-rank-card,
.sk-block {
  background: linear-gradient(
    90deg,
    var(--bg-elevated) 0%,
    color-mix(in srgb, var(--bg-elevated) 70%, var(--text-muted)) 50%,
    var(--bg-elevated) 100%
  );
  background-size: 200% 100%;
  animation: sk-shimmer 1.2s ease-in-out infinite;
}

@keyframes sk-shimmer {
  0% { background-position: 100% 0; }
  100% { background-position: -100% 0; }
}

.sk-grid {
  display: grid;
  gap: 14px 12px;
}
.sk-grid.playlists {
  grid-template-columns: repeat(5, minmax(0, 1fr));
}
.sk-grid.albums {
  grid-template-columns: repeat(5, minmax(0, 1fr));
}
.sk-cell { min-width: 0; }
.sk-cover {
  aspect-ratio: 1;
  border-radius: 10px;
  margin-bottom: 8px;
}
.sk-cover.sm {
  width: 48px;
  height: 48px;
  aspect-ratio: auto;
  border-radius: 8px;
  margin-bottom: 0;
  flex-shrink: 0;
}
.sk-line {
  height: 12px;
  border-radius: 6px;
  margin-top: 6px;
  width: 88%;
}
.sk-line.short {
  width: 58%;
  height: 10px;
  margin-top: 6px;
}

.sk-song-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 8px 12px;
}
.sk-song-row {
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
}
.sk-song-meta {
  flex: 1;
  min-width: 0;
}

.sk-rank-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.sk-rank-row {
  display: grid;
  grid-template-columns: 64px minmax(0, 1fr);
  gap: 10px;
  align-items: center;
  padding: 8px;
  border-radius: 10px;
  background: color-mix(in srgb, var(--bg-elevated, var(--bg-card)) 80%, transparent);
}
.sk-cover.rank {
  width: 64px;
  height: 64px;
  border-radius: 8px;
}
.sk-rank-meta {
  display: flex;
  flex-direction: column;
  gap: 6px;
  min-width: 0;
}

.sk-block {
  height: 120px;
  border-radius: 12px;
}

@media (min-width: 720px) {
  .sk-rank-list {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
  }
  .sk-rank-row {
    grid-template-columns: 88px minmax(0, 1fr);
  }
  .sk-cover.rank {
    width: 88px;
    height: 88px;
  }
}

@media (max-width: 960px) {
  .sk-grid.playlists,
  .sk-grid.albums { grid-template-columns: repeat(4, minmax(0, 1fr)); }
  .sk-song-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 720px) {
  .sk-grid.playlists,
  .sk-grid.albums { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}
@media (max-width: 640px) {
  .sk-song-grid { grid-template-columns: 1fr; }
}
@media (max-width: 480px) {
  .sk-grid.playlists,
  .sk-grid.albums { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
</style>
