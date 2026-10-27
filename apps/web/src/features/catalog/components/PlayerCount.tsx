import { useTranslation } from 'react-i18next'
import type { GameSummary } from '../queries'

export function PlayerCount({ game }: { game: Pick<GameSummary, 'min_players' | 'max_players'> }) {
  const { t } = useTranslation()

  return (
    <>
      {game.min_players === game.max_players
        ? t('catalog.players', { count: game.min_players })
        : t('catalog.playersRange', { min: game.min_players, max: game.max_players })}
    </>
  )
}
