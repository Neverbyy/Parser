import { describe, expect, it } from 'vitest'

import { formatDate, formatNumber, formatRating, pluralize } from './format'

describe('pluralize', () => {
  const forms: [string, string, string] = ['отзыв', 'отзыва', 'отзывов']

  it('склоняет по русским правилам', () => {
    expect(pluralize(1, forms)).toBe('отзыв')
    expect(pluralize(2, forms)).toBe('отзыва')
    expect(pluralize(5, forms)).toBe('отзывов')
    expect(pluralize(21, forms)).toBe('отзыв')
    expect(pluralize(102, forms)).toBe('отзыва')
  })

  it('не путается на числах от 11 до 14', () => {
    expect(pluralize(11, forms)).toBe('отзывов')
    expect(pluralize(12, forms)).toBe('отзывов')
    expect(pluralize(14, forms)).toBe('отзывов')
    expect(pluralize(111, forms)).toBe('отзывов')
  })

  it('правильно склоняет ноль', () => {
    expect(pluralize(0, forms)).toBe('отзывов')
  })
})

describe('formatRating', () => {
  it('всегда показывает один знак после запятой', () => {
    expect(formatRating(4.3)).toBe('4.3')
    expect(formatRating(5)).toBe('5.0')
  })

  it('показывает прочерк, когда оценки нет', () => {
    expect(formatRating(null)).toBe('—')
  })
})

describe('formatNumber', () => {
  it('разделяет разряды', () => {
    // В ru-RU разделитель — неразрывный пробел, сравниваем по цифрам.
    expect(formatNumber(21220).replace(/\s| /g, '')).toBe('21220')
  })

  it('показывает прочерк вместо пустого значения', () => {
    expect(formatNumber(null)).toBe('—')
    expect(formatNumber(undefined)).toBe('—')
  })
})

describe('formatDate', () => {
  it('переводит ISO-дату в читаемый вид', () => {
    expect(formatDate('2026-09-08T23:35:36.482Z')).toContain('2026')
  })

  it('не падает на мусоре', () => {
    expect(formatDate('не дата')).toBe('—')
    expect(formatDate(null)).toBe('—')
  })
})
