import { http } from './client'

import type { Envelope, Organization } from '@/types/api'

/** Организация, сохранённая пользователем. `null`, если ссылку ещё не вводили. */
export async function fetchOrganization(): Promise<Organization | null> {
  const { data } = await http.get<Envelope<Organization | null>>('/api/organization')

  return data.data
}

/**
 * Сохраняет ссылку и запускает разбор карточки.
 *
 * При синхронной очереди запрос держится 10–20 секунд и возвращает уже
 * готовый результат; при фоновой — вернётся организация со статусом
 * `pending`, и дальше состояние доедет опросом.
 */
export async function saveOrganization(url: string): Promise<Organization> {
  const { data } = await http.post<Envelope<Organization>>('/api/organization', { url })

  return data.data
}

export async function refreshOrganization(): Promise<Organization> {
  const { data } = await http.post<Envelope<Organization>>('/api/organization/refresh')

  return data.data
}
