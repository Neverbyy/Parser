/**
 * Проверка ссылки на организацию прямо в форме.
 *
 * Повторяет правило бэкенда (App\Rules\YandexOrganizationUrl), чтобы человек
 * увидел ошибку сразу, а не после запроса. Источник истины при этом остаётся
 * на сервере: его ответ 422 всё равно показывается под полем.
 */

const YANDEX_HOST =
  /(?:^|\.)(?:yandex\.(?:ru|com|by|kz|uz|eu|com\.tr|com\.ge|com\.am|co\.il)|ya\.ru)$/i

const ORGANIZATION_PATHS = [
  /^\/(?:maps|harita)\/org\/(?:[^/]+\/)?\d+/i,
  /^\/org\/(?:[^/]+\/)?\d+/i,
  /^\/profile\/(?:[^/]+\/)?\d+/i,
]

/** Короткая ссылка «Поделиться»: идентификатора в ней нет, его достаёт бэкенд. */
const SHORT_LINK = /^\/(?:maps|harita)\/-\//i

function parse(raw: string): URL | null {
  const trimmed = raw.trim()
  const withScheme = /^https?:\/\//i.test(trimmed) ? trimmed : `https://${trimmed.replace(/^\/+/, '')}`

  try {
    return new URL(withScheme)
  } catch {
    return null
  }
}

/** Возвращает текст ошибки или `null`, если ссылка выглядит корректной. */
export function validateOrganizationUrl(raw: string): string | null {
  if (!raw.trim()) {
    return 'Укажите ссылку на организацию.'
  }

  const url = parse(raw)

  if (!url) {
    return 'Не удалось разобрать ссылку. Вставьте адрес целиком, вместе с https://.'
  }

  if (!YANDEX_HOST.test(url.hostname)) {
    return 'Ссылка должна вести на Яндекс.Карты.'
  }

  if (SHORT_LINK.test(url.pathname)) {
    return null
  }

  const hasOrganizationId =
    ORGANIZATION_PATHS.some((pattern) => pattern.test(url.pathname)) ||
    /^\d+$/.test(url.searchParams.get('oid') ?? '')

  if (!hasOrganizationId) {
    return 'В ссылке не нашёлся идентификатор организации. Откройте карточку компании и скопируйте адрес из строки браузера.'
  }

  return null
}
