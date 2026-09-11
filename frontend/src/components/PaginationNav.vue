<script setup lang="ts">
import { computed } from 'vue'

import { formatNumber } from '@/utils/format'

const props = defineProps<{
  currentPage: number
  lastPage: number
  total: number
  from: number | null
  to: number | null
  /** Пока грузится соседняя страница, кнопки блокируются. */
  disabled?: boolean
}>()

const emit = defineEmits<{ 'update:page': [page: number] }>()

const GAP = '…'

type PageItem = number | typeof GAP

/**
 * Список страниц с многоточиями: первая, последняя и окно вокруг текущей.
 * При 12 страницах (максимум, который отдаёт Яндекс) влезает и без свёртки,
 * но логика не развалится и на большем числе.
 */
const pages = computed<PageItem[]>(() => {
  const { currentPage, lastPage } = props

  if (lastPage <= 7) {
    return Array.from({ length: lastPage }, (_, index) => index + 1)
  }

  const items: PageItem[] = [1]
  const start = Math.max(2, currentPage - 1)
  const end = Math.min(lastPage - 1, currentPage + 1)

  if (start > 2) {
    items.push(GAP)
  }

  for (let page = start; page <= end; page++) {
    items.push(page)
  }

  if (end < lastPage - 1) {
    items.push(GAP)
  }

  items.push(lastPage)

  return items
})

function go(page: number): void {
  if (page >= 1 && page <= props.lastPage && page !== props.currentPage) {
    emit('update:page', page)
  }
}
</script>

<template>
  <nav class="pagination" aria-label="Постраничная навигация по отзывам">
    <p class="pagination__counter">
      Показаны {{ formatNumber(from) }}–{{ formatNumber(to) }} из {{ formatNumber(total) }}
    </p>

    <div class="pagination__controls">
      <button
        type="button"
        class="pagination__button"
        :disabled="disabled || currentPage <= 1"
        aria-label="Предыдущая страница"
        @click="go(currentPage - 1)"
      >
        ←
      </button>

      <template v-for="(item, index) in pages" :key="`${item}-${index}`">
        <span v-if="item === GAP" class="pagination__gap" aria-hidden="true">{{ GAP }}</span>
        <button
          v-else
          type="button"
          class="pagination__button"
          :class="{ 'pagination__button--current': item === currentPage }"
          :disabled="disabled"
          :aria-current="item === currentPage ? 'page' : undefined"
          :aria-label="`Страница ${item}`"
          @click="go(item)"
        >
          {{ item }}
        </button>
      </template>

      <button
        type="button"
        class="pagination__button"
        :disabled="disabled || currentPage >= lastPage"
        aria-label="Следующая страница"
        @click="go(currentPage + 1)"
      >
        →
      </button>
    </div>
  </nav>
</template>

<style scoped>
.pagination {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding-top: 16px;
  border-top: 1px solid var(--border);
}

.pagination__counter {
  font-size: 13px;
  color: var(--text-muted);
}

.pagination__controls {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 4px;
}

.pagination__button {
  min-width: 34px;
  height: 34px;
  padding: 0 8px;
  background: var(--surface);
  border: 1px solid var(--border-strong);
  border-radius: var(--radius-sm);
  font-size: 14px;
  cursor: pointer;
  transition:
    background-color 0.15s ease,
    border-color 0.15s ease;
}

.pagination__button:hover:not(:disabled) {
  background: var(--surface-muted);
}

.pagination__button:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.pagination__button--current {
  background: var(--accent);
  border-color: var(--accent);
  color: #fff;
  font-weight: 600;
}

.pagination__button--current:disabled {
  opacity: 1;
}

.pagination__gap {
  padding: 0 2px;
  color: var(--text-subtle);
}

@media (prefers-color-scheme: dark) {
  .pagination__button--current {
    color: #14161a;
  }
}
</style>
