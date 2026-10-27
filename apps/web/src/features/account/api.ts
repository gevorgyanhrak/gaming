import { useMutation } from '@tanstack/react-query'
import { useQuery } from '@tanstack/react-query'
import type { Channel, CurrentUser } from '@/features/auth/api'
import { useSetCurrentUser } from '@/features/auth/hooks'
import { apiRequest } from '@/shared/api/http'
import type { ApiBody, ApiResponse, Schemas } from '@/shared/api/types'

export type PublicUser = Schemas['PublicUserResource']
type UserResponse = ApiResponse<'v1.me.show'>

const unwrap = (response: UserResponse) => response.data

export const updateProfile = (body: ApiBody<'v1.me.profile.update'>) =>
  apiRequest<UserResponse>('/api/v1/me/profile', { method: 'PATCH', body }).then(unwrap)

export const uploadAvatar = (file: File) => {
  const body = new FormData()
  body.append('avatar', file)
  return apiRequest<UserResponse>('/api/v1/me/avatar', { method: 'POST', body }).then(unwrap)
}

export const removeAvatar = () =>
  apiRequest<UserResponse>('/api/v1/me/avatar', { method: 'DELETE' }).then(unwrap)

export const updateSettings = (body: ApiBody<'v1.me.settings.update'>) =>
  apiRequest<UserResponse>('/api/v1/me/settings', { method: 'PATCH', body }).then(unwrap)

export const changePassword = (body: ApiBody<'v1.me.password.update'>) =>
  apiRequest<void>('/api/v1/me/password', { method: 'PUT', body })

export const requestContactChange = (channel: Channel, value: string) =>
  apiRequest<unknown>('/api/v1/me/contact', { method: 'POST', body: { channel, [channel]: value } })

export const confirmContactChange = (channel: Channel, code: string) =>
  apiRequest<UserResponse>('/api/v1/me/contact/verify', {
    method: 'POST',
    body: { channel, code },
  }).then(unwrap)

/** A mutation whose result is the updated account, stored as the current user. */
export function useAccountMutation<Variables>(
  mutationFn: (variables: Variables) => Promise<CurrentUser>,
) {
  const setCurrentUser = useSetCurrentUser()

  return useMutation({ mutationFn, onSuccess: (user) => setCurrentUser(user) })
}

export function usePublicProfile(username: string) {
  return useQuery({
    queryKey: ['users', username],
    queryFn: ({ signal }) =>
      apiRequest<ApiResponse<'v1.users.show'>>(`/api/v1/users/${encodeURIComponent(username)}`, {
        signal,
      }).then((r) => r.data),
  })
}
