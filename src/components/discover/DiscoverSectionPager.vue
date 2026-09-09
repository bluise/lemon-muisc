<template>
  <div v-if="pageCount > 1" class="section-pager" role="tablist" :aria-label="label">
    <button
      v-for="i in pageCount"
      :key="i"
      type="button"
      class="pager-dot"
      :class="{ active: i - 1 === modelValue }"
      :aria-label="`第 ${i} 页`"
      :aria-selected="i - 1 === modelValue"
      @click="$emit('update:modelValue', i - 1)"
    />
  </div>
</template>

<script setup>
defineProps({
  modelValue: { type: Number, default: 0 },
  pageCount: { type: Number, default: 1 },
  label: { type: String, default: '分页' },
})
defineEmits(['update:modelValue'])
</script>

<style scoped>
.section-pager {
  display: flex;
  justify-content: center;
  gap: 8px;
  margin-top: 14px;
}
.pager-dot {
  width: 8px;
  height: 8px;
  padding: 0;
  border: none;
  border-radius: 50%;
  background: color-mix(in srgb, var(--text-muted) 45%, transparent);
  cursor: pointer;
}
.pager-dot.active {
  background: var(--text-primary);
  transform: scale(1.15);
}
</style>
