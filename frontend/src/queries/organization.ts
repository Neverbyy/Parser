import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'

import { fetchOrganization, refreshOrganization, saveOrganization } from '@/api/organization'
import { queryKeys } from '@/queries/keys'

import type { ApiError, Organization, ParseStatus } from '@/types/api'

/** Статусы, при которых разбор ещё идёт и данные вот-вот изменятся. */
const IN_PROGRESS: ParseStatus[] = ['pending', 'parsing']

const POLL_INTERVAL_MS = 2000

export function isInProgress(status: ParseStatus | undefined): boolean {
  return status !== undefined && IN_PROGRESS.includes(status)
}

/**
 * Сохранённая организация.
 *
 * Пока бэкенд сообщает, что разбор идёт, запрос опрашивается сам. При
 * синхронной очереди до опроса дело не доходит — ответ приходит уже готовым,
 * — но при переключении на фоновую очередь интерфейс продолжит работать
 * без единой правки.
 */
export function useOrganizationQuery() {
  return useQuery<Organization | null, ApiError>({
    queryKey: queryKeys.organization,
    queryFn: fetchOrganization,
    refetchInterval: (query) => (isInProgress(query.state.data?.status) ? POLL_INTERVAL_MS : false),
  })
}

export function useSaveOrganization() {
  const queryClient = useQueryClient()

  return useMutation<Organization, ApiError, string>({
    // Аргумент передаётся явно: вторым параметром Query отдаёт свой контекст,
    // и в слой API он попадать не должен.
    mutationFn: (url) => saveOrganization(url),
    onSuccess: (organization) => {
      // Ответ мутации — это уже актуальная организация: кладём её в кеш
      // напрямую, чтобы не ходить на сервер второй раз.
      queryClient.setQueryData(queryKeys.organization, organization)
      // А вот отзывы сменились целиком, их надо перечитать.
      void queryClient.invalidateQueries({ queryKey: queryKeys.reviews })
    },
  })
}

export function useRefreshOrganization() {
  const queryClient = useQueryClient()

  return useMutation<Organization, ApiError, void>({
    mutationFn: () => refreshOrganization(),
    onSuccess: (organization) => {
      queryClient.setQueryData(queryKeys.organization, organization)
      void queryClient.invalidateQueries({ queryKey: queryKeys.reviews })
    },
  })
}
