import { useEffect } from 'react'

/** Uses the admin-uploaded favicon when there is one; index.html ships the default. */
export function useFavicon(url: string | null | undefined) {
  useEffect(() => {
    if (!url) return
    const link = document.querySelector<HTMLLinkElement>('link[rel="icon"]')
    if (!link) return

    const previous = { href: link.href, type: link.type }
    link.href = url
    link.removeAttribute('type')

    return () => {
      link.href = previous.href
      link.type = previous.type
    }
  }, [url])
}
