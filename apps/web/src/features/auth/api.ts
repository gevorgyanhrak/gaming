import { isApiError } from '@/shared/api/errors'
import { apiRequest } from '@/shared/api/http'
import type { ApiBody, ApiResponse, Schemas } from '@/shared/api/types'

export type CurrentUser = Schemas['UserResource']
export type Channel = 'email' | 'phone'

type UserResponse = ApiResponse<'v1.me.show'>

/** The signed-in user, or null for guests (and for sessions the server just ended). */
export async function fetchCurrentUser(signal?: AbortSignal): Promise<CurrentUser | null> {
  try {
    return (await apiRequest<UserResponse>('/api/v1/me', { signal })).data
  } catch (error) {
    if (isApiError(error) && (error.status === 401 || error.status === 403)) return null
    throw error
  }
}

export const login = (body: ApiBody<'v1.auth.login'>) =>
  apiRequest<UserResponse>('/api/v1/auth/login', { method: 'POST', body }).then((r) => r.data)

export const register = (body: ApiBody<'v1.auth.register'>) =>
  apiRequest<UserResponse>('/api/v1/auth/register', { method: 'POST', body }).then((r) => r.data)

export const logout = () => apiRequest<void>('/api/v1/auth/logout', { method: 'POST' })

export const sendVerificationCode = (channel: Channel) =>
  apiRequest<unknown>('/api/v1/auth/verification/send', { method: 'POST', body: { channel } })

export const verifyCode = (channel: Channel, code: string) =>
  apiRequest<UserResponse>('/api/v1/auth/verification/verify', {
    method: 'POST',
    body: { channel, code },
  }).then((r) => r.data)

export const forgotPassword = (login: string) =>
  apiRequest<unknown>('/api/v1/auth/password/forgot', { method: 'POST', body: { login } })

export const resetPassword = (body: ApiBody<'v1.auth.password.reset'>) =>
  apiRequest<unknown>('/api/v1/auth/password/reset', { method: 'POST', body })

export const isUsernameAvailable = (username: string) =>
  apiRequest<ApiResponse<'v1.auth.username-available'>>(
    `/api/v1/auth/username-available?username=${encodeURIComponent(username)}`,
  ).then((r) => r.data.available)
