/**
 * Game search that understands how people type game names (owner 2026-11-17): Armenian and Russian names in Latin
 * letters ("shaxmat", "shahmat", "nardi", "shashki"), the names in their own script, English names and common
 * variants, with small typing mistakes forgiven. One place for every alias: a new game adds a line to GAME_ALIASES
 * (keyed by its slug); the catalogue's own name and slug always count too, so a game with no line still matches.
 *
 * Matching runs on a "skeleton" of each text: Armenian and Cyrillic letters spelled in Latin, accents dropped, and
 * spellings that sound the same folded together (kh / h / x, sh, ch, zh, gh, c / k / q, w / v, y / i, double
 * letters), so "shakhmaty", "shahmat" and "Շախմատ" meet at the same word.
 */

/** Other names a game is searched by, keyed by its slug. Any script; folded to the skeleton when compared. */
export const GAME_ALIASES: Readonly<Record<string, readonly string[]>> = {
  chess: ['shaxmat', 'shakhmat', 'shahmat', 'shakhmaty', 'chess', 'шахматы', 'Շախմատ'],
  nardi: ['nardi', 'nardy', 'nard', 'backgammon', 'tavla', 'tavlu', 'long nardi', 'нарды', 'Նարդի'],
  checkers: ['shashki', 'shashka', 'checkers', 'draughts', 'dama', 'шашки', 'Շաշկի'],
  durak: ['durak', 'durachok', 'fool', 'дурак', 'Դուրակ'],
  poker: ['poker', 'holdem', "texas hold'em", 'покер', 'Պոկեր'],
  blot: ['blot', 'belot', 'belote', 'bazar blot', 'блот', 'Բլոտ'],
  blackjack: [
    'blackjack',
    'black jack',
    '21',
    'twenty one',
    'ochko',
    'очко',
    'блэкджек',
    'Բլեքջեք',
  ],
  domino: ['domino', 'dominoes', 'домино', 'Դոմինո'],
  ludo: ['ludo', 'parcheesi', 'лудо', 'Լուդո'],
  pool: ['pool', 'billiards', 'biliard', '8 ball', 'eight ball', 'бильярд', 'Բիլիարդ'],
  rummy: ['rummy', 'rami', 'remi', 'рамми', 'Ռամի'],
}

const ARMENIAN: Record<string, string> = {
  ա: 'a',
  բ: 'b',
  գ: 'g',
  դ: 'd',
  ե: 'e',
  զ: 'z',
  է: 'e',
  ը: 'e',
  թ: 't',
  ժ: 'zh',
  ի: 'i',
  լ: 'l',
  խ: 'kh',
  ծ: 'ts',
  կ: 'k',
  հ: 'h',
  ձ: 'dz',
  ղ: 'gh',
  ճ: 'ch',
  մ: 'm',
  յ: 'y',
  ն: 'n',
  շ: 'sh',
  ո: 'o',
  չ: 'ch',
  պ: 'p',
  ջ: 'j',
  ռ: 'r',
  ս: 's',
  վ: 'v',
  տ: 't',
  ր: 'r',
  ց: 'ts',
  ւ: 'v',
  փ: 'p',
  ք: 'k',
  օ: 'o',
  ֆ: 'f',
  և: 'ev',
}
const CYRILLIC: Record<string, string> = {
  а: 'a',
  б: 'b',
  в: 'v',
  г: 'g',
  д: 'd',
  е: 'e',
  ё: 'e',
  ж: 'zh',
  з: 'z',
  и: 'i',
  й: 'y',
  к: 'k',
  л: 'l',
  м: 'm',
  н: 'n',
  о: 'o',
  п: 'p',
  р: 'r',
  с: 's',
  т: 't',
  у: 'u',
  ф: 'f',
  х: 'kh',
  ц: 'ts',
  ч: 'ch',
  ш: 'sh',
  щ: 'sh',
  ъ: '',
  ы: 'y',
  ь: '',
  э: 'e',
  ю: 'yu',
  я: 'ya',
}

/** Latin letters for any Armenian or Cyrillic ones, lower case, accents dropped. */
function latin(text: string): string {
  return text
    .toLocaleLowerCase()
    .normalize('NFD')
    .replace(/\p{M}/gu, '')
    .replace(/ու/g, 'u')
    .replace(/[ա-և]|[а-яё]/g, (ch) => ARMENIAN[ch] ?? CYRILLIC[ch] ?? ch)
}

/**
 * The text as compared: Latin, words separated by single spaces, same-sounding spellings folded together. Two
 * spellings of one name give the same skeleton ("shahmat", "shakhmat", "shaxmat" → "Saxmat").
 */
export function skeleton(text: string): string {
  return (
    latin(text)
      .replace(/[^a-z0-9]+/g, ' ')
      .trim()
      // Placeholders keep the two-letter sounds whole while single letters are folded.
      .replace(/s?ch/g, (m) => (m === 'sch' ? 'S' : 'C'))
      .replace(/sh/g, 'S')
      .replace(/zh/g, 'Z')
      .replace(/gh/g, 'G')
      .replace(/kh/g, 'x')
      .replace(/ph/g, 'f')
      .replace(/ck|q|c/g, 'k')
      .replace(/h/g, 'x')
      .replace(/w/g, 'v')
      .replace(/y/g, 'i')
      // "shaxx", "pokker": a doubled letter is one.
      .replace(/(.)\1+/g, '$1')
  )
}

/** Every text a game is found by: its name in the current language, its slug, and its aliases. */
export function termsOf(slug: string, name: string): string[] {
  return [name, slug, ...(GAME_ALIASES[slug] ?? [])]
}

/** Edits (insert, delete, change, swap two neighbours) between `a` and the closest start of `b`. */
function prefixDistance(a: string, b: string): number {
  const rows: number[][] = [Array.from({ length: b.length + 1 }, (_, j) => j)]
  for (let i = 1; i <= a.length; i++) {
    const row = [i]
    for (let j = 1; j <= b.length; j++) {
      const cost = a[i - 1] === b[j - 1] ? 0 : 1
      let best = Math.min(row[j - 1]! + 1, rows[i - 1]![j]! + 1, rows[i - 1]![j - 1]! + cost)
      if (i > 1 && j > 1 && a[i - 1] === b[j - 2] && a[i - 2] === b[j - 1])
        best = Math.min(best, rows[i - 2]![j - 2]! + 1)
      row.push(best)
    }
    rows.push(row)
  }
  // Against any start of b: the needle is a partial word.
  return Math.min(...rows[a.length]!)
}

export type Match = {
  /** Lower is better: 0 the name starts with it, 1 an alias does, 2 a word does, 3 it's inside, 4 a near miss. */
  score: number
  /** The text that matched (the name, or the alias that brought the game up). */
  term: string
}

/** How well a typed query finds a game by these terms, or null when it doesn't. */
export function matchTerms(query: string, terms: string[]): Match | null {
  const needle = skeleton(query)
  if (!needle) return null
  let best: Match | null = null
  const take = (score: number, term: string) => {
    if (!best || score < best.score) best = { score, term }
  }
  terms.forEach((term, i) => {
    const text = skeleton(term)
    if (text.startsWith(needle)) take(i === 0 ? 0 : 1, term)
    else if (text.split(' ').some((word) => word.startsWith(needle))) take(2, term)
    // Inside a word: the name itself, or another name once the text is long enough to mean something.
    else if (
      (i === 0 || needle.length >= 3) &&
      text.replace(/ /g, '').includes(needle.replace(/ /g, ''))
    )
      take(3, term)
    else if (needle.length >= 4) {
      // A typing slip: one edit, two in a long word, against the start of each word.
      // The first letter has to be right ("roulette" isn't "belote").
      const allowed = needle.length >= 8 ? 2 : 1
      const near = (word: string) =>
        word[0] === needle[0] && prefixDistance(needle, word) <= allowed
      if (text.split(' ').some(near)) take(4, term)
    }
  })
  return best
}

/** The items a query finds, best first (the catalogue's order among equals), with what each matched. */
export function search<T>(
  items: T[],
  query: string,
  termsFor: (item: T) => string[],
): { item: T; match: Match }[] {
  return items
    .map((item) => ({ item, match: matchTerms(query, termsFor(item)) }))
    .filter((entry): entry is { item: T; match: Match } => entry.match !== null)
    .toSorted((a, b) => a.match.score - b.match.score)
}
