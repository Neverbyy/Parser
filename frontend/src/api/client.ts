import axios, { AxiosError, type AxiosInstance } from 'axios'

import type { ApiError, ApiErrorCode } from '@/types/api'

/**
 * По умолчанию запросы идут относительными путями и попадают на бэкенд через
 * прокси Vite — браузер при этом видит один origin. Если понадобится ходить
 * напрямую на другой хост, достаточно задать VITE_API_BASE_URL.
 */
const baseURL: string = import.meta.env.VITE_API_BASE_URL ?? '/'

export const http: AxiosInstance = axios.create({
  baseURL,
  // Сессионная кука Sanctum: без этого аутентификация не работает вовсе.
  withCredentials: true,
  // Разрешаем axios подставлять X-XSRF-TOKEN из куки, в том числе
  // когда бэкенд находится на другом origin.
  withXSRFToken: true,
  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
})

const CSRF_COOKIE = 'XSRF-TOKEN'
const MUTATING_METHODS = new Set(['post', 'put', 'patch', 'delete'])

function hasCsrfCookie(): boolean {
  return document.cookie.split('; ').some((cookie) => cookie.startsWith(`${CSRF_COOKIE}=`))
}

let pendingCsrfRequest: Promise<unknown> | null = null

/**
 * Получает CSRF-куку, если её ещё нет.
 *
 * Параллельные запросы делят один поход на сервер: иначе три одновременные
 * мутации сходили бы за куком трижды.
 */
export async function ensureCsrfCookie(): Promise<void> {
  if (hasCsrfCookie()) {
    return
  }

  pendingCsrfRequest ??= axios
    .get('/sanctum/csrf-cookie', { baseURL, withCredentials: true })
    .finally(() => {
      pendingCsrfRequest = null
    })

  await pendingCsrfRequest
}

http.interceptors.request.use(async (config) => {
  if (MUTATING_METHODS.has((config.method ?? 'get').toLowerCase())) {
    await ensureCsrfCookie()
  }

  return config
})

let unauthorizedHandler: (() => void) | null = null

/**
 * Что делать, когда сессия кончилась. Обработчик задаётся снаружи,
 * чтобы транспорт не зависел от стора и роутера.
 */
export function onUnauthorized(handler: () => void): void {
  unauthorizedHandler = handler
}

interface BackendErrorBody {
  message?: string
  code?: ApiErrorCode
  errors?: Record<string, string[]>
}

const STATUS_FALLBACKS: Record<number, string> = {
  401: 'Нужно войти заново.',
  403: 'Недостаточно прав.',
  404: 'Ничего не нашлось.',
  419: 'Сессия устарела, обновите страницу и попробуйте снова.',
  429: 'Слишком много попыток. Подождите минуту.',
  500: 'Внутренняя ошибка сервера.',
}

function toApiError(error: AxiosError<BackendErrorBody>): ApiError {
  // Сети нет, сервер не ответил, запрос отменён по таймауту.
  if (!error.response) {
    return {
      message: 'Не удалось связаться с сервером. Проверьте, запущен ли бэкенд.',
      status: 0,
    }
  }

  const { status, data } = error.response

  return {
    message: data?.message || STATUS_FALLBACKS[status] || 'Что-то пошло не так.',
    status,
    code: data?.code,
    errors: data?.errors,
  }
}

http.interceptors.response.use(
  (response) => response,
  (error: AxiosError<BackendErrorBody>) => {
    const apiError = toApiError(error)

    if (apiError.status === 401) {
      unauthorizedHandler?.()
    }

    return Promise.reject(apiError)
  },
)

/** Сужает `unknown` из catch до нашей ошибки — удобно в компонентах. */
export function isApiError(error: unknown): error is ApiError {
  return typeof error === 'object' && error !== null && 'message' in error && 'status' in error
}
