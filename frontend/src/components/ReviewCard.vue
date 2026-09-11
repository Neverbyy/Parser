<script setup lang="ts">
import { computed } from 'vue'

import StarRating from '@/components/StarRating.vue'
import { formatDate } from '@/utils/format'

import type { Review } from '@/types/api'

const props = defineProps<{ review: Review }>()

/** У части отзывов автора нет — Яндекс отдаёт их без имени. */
const authorName = computed(() => props.review.author_name ?? 'Аноним')

const initial = computed(() => authorName.value.trim().charAt(0).toUpperCase())
</script>

<template>
  <article class="review">
    <img
      v-if="review.author_avatar_url"
      class="review__avatar"
      :src="review.author_avatar_url"
      :alt="''"
      loading="lazy"
      width="40"
      height="40"
    />
    <div v-else class="review__avatar review__avatar--fallback" aria-hidden="true">
      {{ initial }}
    </div>

    <div class="review__body">
      <header class="review__header">
        <span class="review__author">{{ authorName }}</span>
        <time v-if="review.published_at" class="review__date" :datetime="review.published_at">
          {{ formatDate(review.published_at) }}
        </time>
      </header>

      <StarRating :value="review.rating" :size="14" />

      <p v-if="review.text" class="review__text">{{ review.text }}</p>
      <p v-else class="review__text review__text--empty">Без текста — только оценка.</p>
    </div>
  </article>
</template>

<style scoped>
.review {
  display: flex;
  gap: 12px;
  padding: 16px 0;
  border-top: 1px solid var(--border);
}

.review:first-child {
  border-top: none;
  padding-top: 0;
}

.review__avatar {
  flex: none;
  width: 40px;
  height: 40px;
  border-radius: 50%;
  object-fit: cover;
  background: var(--surface-muted);
}

.review__avatar--fallback {
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  color: var(--text-muted);
}

.review__body {
  display: flex;
  flex-direction: column;
  gap: 6px;
  min-width: 0;
}

.review__header {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 10px;
}

.review__author {
  font-weight: 600;
}

.review__date {
  font-size: 13px;
  color: var(--text-subtle);
}

.review__text {
  /* Отзывы приходят с переносами строк — сохраняем их. */
  white-space: pre-line;
  overflow-wrap: anywhere;
}

.review__text--empty {
  color: var(--text-subtle);
  font-style: italic;
}
</style>
