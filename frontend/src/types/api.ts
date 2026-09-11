/**
 * Формы ответов бэкенда.
 *
 * Имена полей — snake_case, как их отдают Laravel-ресурсы: переименовывать
 * на клиенте не стали, чтобы контракт читался один в один с API.
 */

/** Laravel-ресурсы заворачивают одиночный объект в `data`. */
export interface Envelope<T> {
  data: T
}

export interface User {
  id: number
  name: string
  email: string
}

/** Состояние разбора карточки. По нему интерфейс решает, что показывать. */
export type ParseStatus = 'pending' | 'parsing' | 'ready' | 'failed'

export interface Organization {
  id: number
  yandex_id: string
  url: string
  name: string | null
  address: string | null

  /** Средний балл организации. */
  rating: number | null

  /** Сколько людей поставили оценку. */
  ratings_count: number | null

  /** Сколько из них написали текстовый отзыв. */
  reviews_count: number | null

  /**
   * Сколько отзывов реально выгружено. Яндекс отдаёт наружу не больше 600,
   * поэтому у крупных организаций это число меньше reviews_count.
   */
  fetched_reviews_count: number

  status: ParseStatus
  error_message: string | null
  parsed_at: string | null
}

export interface Review {
  id: number
  author_name: string | null
  author_avatar_url: string | null
  rating: number | null
  text: string | null
  published_at: string | null
}

/** Обёртка Laravel-пагинации. */
export interface Paginated<T> {
  data: T[]
  meta: {
    current_page: number
    from: number | null
    last_page: number
    per_page: number
    to: number | null
    total: number
  }
}

/** Коды ошибок парсера — бэкенд отдаёт их в поле `code`. */
export type ApiErrorCode =
  | 'invalid_url'
  | 'organization_unavailable'
  | 'markup_changed'
  | 'empty_response'
  | 'captcha'
  | 'request_failed'
  | 'yandex_error'

/**
 * Ошибка в едином виде: что бы ни случилось — валидация, сбой парсера,
 * обрыв сети — интерфейс получает одну и ту же структуру.
 */
export interface ApiError {
  message: string
  status: number
  code?: ApiErrorCode
  /** Ошибки валидации по полям, как их отдаёт Laravel при 422. */
  errors?: Record<string, string[]>
}
