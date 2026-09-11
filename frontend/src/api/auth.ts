import { http } from './client'

import type { Envelope, User } from '@/types/api'

export interface Credentials {
  email: string
  password: string
  remember?: boolean
}

export async function login(credentials: Credentials): Promise<User> {
  const { data } = await http.post<Envelope<User>>('/api/login', credentials)

  return data.data
}

export async function logout(): Promise<void> {
  await http.post('/api/logout')
}

export async function fetchCurrentUser(): Promise<User> {
  const { data } = await http.get<Envelope<User>>('/api/user')

  return data.data
}
