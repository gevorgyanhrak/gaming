import { describe, expect, it } from 'vitest'
import { bundledLocales, languageName } from '.'

describe('Language names', () => {
  it('reads each language in its own name, capitalised', () => {
    expect(bundledLocales.map(languageName)).toEqual(['English', 'Русский', 'Հայերեն'])
  })
})
