import { computed } from 'vue'
import { usePagedTrackRows } from './usePagedTrackRows.js'
import { useVirtualTrackList } from './useVirtualTrackList.js'

const VIRTUAL_THRESHOLD = 60
const PAGE_SIZE = 50

/**
 * 曲目列表展示：小列表全量渲染；中等列表分页；大列表可选虚拟滚动
 */
export function useTrackListView(getTracks, {
  paginateWhen = () => true,
  enableVirtual = true,
  pageSize = PAGE_SIZE,
  virtualThreshold = VIRTUAL_THRESHOLD,
  desktopRowHeight,
  mobileRowHeight,
} = {}) {
  const trackCount = computed(() => getTracks().length)
  const useVirtualMode = computed(() => enableVirtual && trackCount.value > virtualThreshold)

  const {
    page,
    totalPages,
    displayRows: pagedRows,
    resetPage,
  } = usePagedTrackRows(getTracks, {
    pageSize,
    enabled: () => paginateWhen() && trackCount.value > pageSize && !useVirtualMode.value,
  })

  const {
    containerRef,
    useVirtual,
    visibleRows,
    paddingTop,
    paddingBottom,
    onScroll,
    resetScroll,
    measureViewport,
  } = useVirtualTrackList(() => {
    if (useVirtualMode.value) return getTracks().map((item, i) => ({ item, i }))
    return pagedRows.value
  }, {
    threshold: virtualThreshold,
    ...(desktopRowHeight != null ? { desktopRowHeight } : {}),
    ...(mobileRowHeight != null ? { mobileRowHeight } : {}),
  })

  const displayRows = computed(() => (
    useVirtualMode.value ? visibleRows.value : pagedRows.value
  ))

  function resetView() {
    resetPage()
    resetScroll()
  }

  return {
    page,
    totalPages,
    displayRows,
    containerRef,
    useVirtual: useVirtualMode,
    paddingTop,
    paddingBottom,
    onScroll,
    resetView,
    measureViewport,
    showPagination: computed(() => !useVirtualMode.value && totalPages.value > 1),
  }
}
