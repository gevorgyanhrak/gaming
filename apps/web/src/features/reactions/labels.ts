import type { TFunction } from 'i18next'

/** A predefined message in the viewer's language (the server sends its key). */
export const messageText = (t: TFunction, key: string) =>
  t(`reactions.messages.${key}` as 'reactions.messages.gg', { defaultValue: key })

export const emojiCategoryName = (t: TFunction, slug: string) =>
  t(`reactions.emojiCategories.${slug}` as 'reactions.emojiCategories.smileys', {
    defaultValue: slug,
  })

export const messageCategoryName = (t: TFunction, slug: string) =>
  t(`reactions.messageCategories.${slug}` as 'reactions.messageCategories.greetings', {
    defaultValue: slug,
  })
