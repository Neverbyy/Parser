import { http } from './client'

import type { Paginated, Review } from '@/types/api'

/** Размер страницы задан заданием: по 50 отзывов. */
export const REVIEWS_PER_PAGE = 50

export async function fetchReviews(page: number): Promise<Paginated<Review>> {
  const { data } = await http.get<Paginated<Review>>('/api/organization/reviews', {
    params: { page, per_page: REVIEWS_PER_PAGE },
  })

  return data
}
