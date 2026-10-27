import { useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import type { CurrentUser } from '@/features/auth/api'
import { applyServerErrors, useMessage } from '@/features/auth/forms'
import { Alert } from '@/shared/ui/Alert'
import { Avatar } from '@/shared/ui/Avatar'
import { Button } from '@/shared/ui/Button'
import { Card } from '@/shared/ui/Card'
import { removeAvatar, uploadAvatar, useAccountMutation } from '../api'

const MAX_BYTES = 2 * 1024 * 1024
const TYPES = ['image/jpeg', 'image/png', 'image/webp']

export function AvatarForm({ user }: { user: CurrentUser }) {
  const { t } = useTranslation()
  const message = useMessage()
  const input = useRef<HTMLInputElement>(null)
  const [error, setError] = useState<string | null>(null)
  const upload = useAccountMutation(uploadAvatar)
  const remove = useAccountMutation(removeAvatar)
  const busy = upload.isPending || remove.isPending

  const onError = (err: unknown) =>
    setError(message(applyServerErrors(err, () => undefined, []) ?? 'errors.generic'))

  const onFile = (file: File | undefined) => {
    setError(null)
    if (!file) return
    if (!TYPES.includes(file.type)) return setError(t('settings.avatarType'))
    if (file.size > MAX_BYTES) return setError(t('settings.avatarSize'))
    upload.mutate(file, { onError })
  }

  return (
    <Card title={t('settings.avatar')} description={t('settings.avatarHint')}>
      <div className="flex flex-wrap items-center gap-4">
        <Avatar
          url={user.profile.avatar_url}
          name={user.profile.display_name ?? user.username}
          size="lg"
        />
        <div className="flex flex-wrap gap-2">
          <input
            ref={input}
            type="file"
            accept={TYPES.join(',')}
            className="sr-only"
            aria-label={t('settings.uploadAvatar')}
            onChange={(event) => {
              onFile(event.target.files?.[0])
              event.target.value = ''
            }}
          />
          <Button disabled={busy} onClick={() => input.current?.click()}>
            {t('settings.uploadAvatar')}
          </Button>
          {user.profile.avatar_url ? (
            <Button
              variant="secondary"
              disabled={busy}
              onClick={() => remove.mutate(undefined, { onError })}
            >
              {t('settings.removeAvatar')}
            </Button>
          ) : null}
        </div>
      </div>
      {error ? (
        <div className="mt-4">
          <Alert tone="danger">{error}</Alert>
        </div>
      ) : null}
    </Card>
  )
}
