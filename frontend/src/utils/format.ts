const numberFormatter = new Intl.NumberFormat('ru-RU')

const dateFormatter = new Intl.DateTimeFormat('ru-RU', {
  day: 'numeric',
  month: 'long',
  year: 'numeric',
})

export function formatNumber(value: number | null | undefined): string {
  return value === null || value === undefined ? '—' : numberFormatter.format(value)
}

/** Средний балл — всегда с одним знаком: «4.3», а не «4.3000001». */
export function formatRating(value: number | null | undefined): string {
  return value === null || value === undefined ? '—' : value.toFixed(1)
}

export function formatDate(iso: string | null | undefined): string {
  if (!iso) {
    return '—'
  }

  const date = new Date(iso)

  return Number.isNaN(date.getTime()) ? '—' : dateFormatter.format(date)
}

/**
 * Выбирает форму слова по числу: 1 оценка, 2 оценки, 5 оценок.
 *
 * @param forms [для 1, для 2–4, для 5 и далее]
 */
export function pluralize(count: number, forms: [string, string, string]): string {
  const mod10 = count % 10
  const mod100 = count % 100

  if (mod10 === 1 && mod100 !== 11) {
    return forms[0]
  }

  if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) {
    return forms[1]
  }

  return forms[2]
}
