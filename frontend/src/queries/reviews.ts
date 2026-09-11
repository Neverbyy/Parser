import { keepPreviousData, useQuery } from '@tanstack/vue-query'
import { computed, type Ref } from 'vue'

import { fetchReviews } from '@/api/reviews'
import { queryKeys } from '@/queries/keys'

import type { ApiError, Paginated, Review } from '@/types/api'

/**
 * Страница отзывов.
 *
 * placeholderData: keepPreviousData оставляет на экране предыдущую страницу,
 * пока грузится следующая — список не схлопывается в пустоту, и высота
 * страницы не прыгает при листании.
 */
export function useReviewsQuery(page: Ref<number>, enabled: Ref<boolean>) {
  return useQuery<Paginated<Review>, ApiError>({
    queryKey: computed(() => queryKeys.reviewsPage(page.value)),
    queryFn: () => fetchReviews(page.value),
    enabled,
    placeholderData: keepPreviousData,
  })
}
