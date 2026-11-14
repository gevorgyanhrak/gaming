import { useTranslation } from 'react-i18next'

export interface Achievement {
  key: string
  game: { slug: string; name: string } | null
  period: string | null
  value: number | null
}

/** The achievement's name, in the reader's language (with its game where it has one). */
export function useAchievementName() {
  const { t } = useTranslation()
  return (a: Achievement) =>
    a.key.startsWith('veteran_')
      ? t('achievements.veteran', { count: Number(a.key.slice(8)) })
      : t(`achievements.${a.key}` as 'achievements.big_win', { game: a.game?.name ?? '' })
}
