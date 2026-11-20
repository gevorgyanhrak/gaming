/**
 * Jungle Slot's client (house slot, ADR 0024). The art is "Jungle Slot Assets" by danitorres567 (OpenGameArt, CC0),
 * laid out exactly as its 1920×1080 design; the maths lives on our server (App\Platform\Slots\House\Games\JungleSlot).
 * The browser only asks for a spin with a bet and shows the screen and wins it gets back.
 */
import i18n from '@/shared/i18n'
import { HouseError, fishkas, openGame, settleWin, spin, tellLowBalance } from '../../shared/house'

const GAME = 'jungle-slot'
const ART = '/play/jungle-slot/'
const LINES = 20

type Symbol = 'WILD' | 'PINEAPPLE' | 'COCONUT' | 'APRICOT' | 'BANANA' | 'MUSHROOM' | 'K'
type LineWin = { line: number; symbol: Symbol; count: number; amount: number; positions: [number, number][] }
type Round = { grid: Symbol[][]; lineWins: LineWin[]; totalWin: number }

const SYMBOLS: Symbol[] = ['WILD', 'PINEAPPLE', 'COCONUT', 'APRICOT', 'BANANA', 'MUSHROOM', 'K']
const IMAGE: Record<Symbol, string> = {
  WILD: 'sym-wild', PINEAPPLE: 'sym-pineapple', COCONUT: 'sym-coconut', APRICOT: 'sym-apricot',
  BANANA: 'sym-banana', MUSHROOM: 'sym-mushroom', K: 'sym-k',
}
/** The two symbols the art draws glowing; the others glow by CSS. */
const GLOW: Partial<Record<Symbol, string>> = { WILD: 'sym-wild-glow', K: 'sym-k-glow' }
/** For the info screen (the server holds the real table; these are the same numbers, per line bet). */
const PAYS: Record<Symbol, number[]> = {
  WILD: [50, 250, 2500], PINEAPPLE: [25, 100, 500], COCONUT: [20, 75, 300], APRICOT: [15, 50, 200],
  BANANA: [10, 40, 150], MUSHROOM: [5, 25, 100], K: [5, 20, 75],
}
const PAYLINES = [
  [1, 1, 1, 1, 1], [0, 0, 0, 0, 0], [2, 2, 2, 2, 2], [0, 1, 2, 1, 0], [2, 1, 0, 1, 2],
  [1, 0, 0, 0, 1], [1, 2, 2, 2, 1], [0, 0, 1, 2, 2], [2, 2, 1, 0, 0], [1, 2, 1, 0, 1],
  [1, 0, 1, 2, 1], [0, 1, 1, 1, 0], [2, 1, 1, 1, 2], [0, 1, 0, 1, 0], [2, 1, 2, 1, 2],
  [1, 1, 0, 1, 1], [1, 1, 2, 1, 1], [0, 0, 2, 0, 0], [2, 2, 0, 2, 2], [0, 2, 2, 2, 0],
]
const LINE_COLOURS = ['#ffe14d', '#ff5c8a', '#4dd2ff', '#7dff6b', '#ff9f43', '#c084fc', '#f87171', '#34d399', '#fbbf24', '#60a5fa']

/** Where things sit on the design canvas (from the PSD). */
const CELL = { x: 350, y: 270, dx: 250, dy: 202 }
const BUTTONS = {
  spins: [24, 254, 176, 177], auto: [6, 445, 198, 199], minus: [20, 190, 85, 85], info: [24, 656, 176, 177],
  lines: [1727, 254, 176, 177], max: [1717, 445, 198, 199], betLine: [1723, 656, 176, 177], spin: [754, 911, 422, 284],
} as const
const BUTTON_ART: Record<keyof typeof BUTTONS, string> = {
  spins: 'side-buttons-1', auto: 'side-buttons-0', minus: 'side-buttons-2', info: 'side-buttons-3',
  lines: 'side-buttons-5', max: 'side-buttons-4', betLine: 'side-buttons-6', spin: 'spin-button',
}
const LABELS: Record<keyof typeof BUTTONS, string> = {
  spins: 'Number of auto spins', auto: 'Auto start', minus: 'Lower the bet', info: 'Paytable',
  lines: 'Show the 20 lines', max: 'Maximum bet', betLine: 'Raise the bet', spin: 'Spin',
}
const BOXES = ['Lines', 'Line bet', 'Total bet', 'Won', 'Credits', 'Spins'] as const
const BOX_X = [31, 267, 504, 1193, 1429, 1666]

/**
 * Held upright, a canvas of 1600×2320: the scene (table, reels, lines) on top, moved left so the table fills the
 * width; under it the six boxes in two rows and the buttons in two rows, the bet buttons either side of SPIN —
 * everything twice the size or more, so the game fills a phone's width and every button takes a finger.
 */
const UPRIGHT = { width: 1600, height: 2320, sceneX: -160 }
const UPRIGHT_BUTTONS: Record<keyof typeof BUTTONS, readonly number[]> = {
  spins: [40, 1610, 230, 230], auto: [362, 1610, 230, 230], info: [684, 1610, 230, 230],
  lines: [1006, 1610, 230, 230], max: [1328, 1610, 230, 230],
  minus: [120, 1967, 230, 230], spin: [500, 1880, 600, 404], betLine: [1250, 1967, 230, 230],
}
const UPRIGHT_BOXES = [0, 1, 2, 3, 4, 5].map((i) => [40 + (i % 3) * 530, i < 3 ? 1110 : 1350, 460, 219])
const AUTO_COUNTS = [10, 25, 50, 100]

const $ = <T extends HTMLElement>(id: string) => document.getElementById(id) as T
const stage = $<HTMLDivElement>('stage')
const reelsEl = $<HTMLDivElement>('reels')
const linesSvg = document.getElementById('paylines') as unknown as SVGSVGElement
const message = $<HTMLDivElement>('message')
const overlay = $<HTMLDivElement>('overlay')
const monkey = $<HTMLImageElement>('monkey')
const still = matchMedia('(prefers-reduced-motion: reduce)').matches

function place(el: HTMLElement, [x, y, w, h]: readonly number[]) {
  Object.assign(el.style, { left: `${x}px`, top: `${y}px`, width: `${w}px`, height: `${h}px` })
}
for (const [id, box] of [['table', [173, 0, 1579, 1080]], ['cells', [349, 271, 1227, 583]], ['numbers', [244, 286, 1436, 538]], ['title', [669, 29, 689, 222]], ['monkey', [0, 363, 829, 752]]] as const) {
  place($(id), box)
}

// The hint for phones held upright, in the app's language (the frame shares its saved choice).
const hint = $<HTMLParagraphElement>('rotate')
hint.textContent = i18n.t('slots.rotate')

// Reels: a strip per reel; the last three images are what shows when it stops.
const src = (s: Symbol, glow = false) => `${ART}${(glow && GLOW[s]) || IMAGE[s]}.webp`
const reels = Array.from({ length: 5 }, (_, reel) => {
  const el = document.createElement('div')
  el.className = 'reel'
  place(el, [CELL.x + CELL.dx * reel - 10, CELL.y - 10, 228, CELL.dy * 3])
  const strip = document.createElement('div')
  strip.className = 'strip'
  el.append(strip)
  reelsEl.append(el)
  return { el, strip }
})
function fill(reel: number, shown: Symbol[], extra = 0) {
  const { strip } = reels[reel]!
  strip.replaceChildren()
  const all = [...Array.from({ length: extra }, () => SYMBOLS[Math.floor(Math.random() * SYMBOLS.length)]!), ...shown]
  for (const s of all) {
    const img = document.createElement('img')
    img.className = 'sym'
    img.src = src(s)
    img.alt = s.toLowerCase()
    img.dataset.symbol = s
    img.style.marginBottom = `${CELL.dy - 202}px`
    strip.append(img)
  }
  strip.style.transition = 'none'
  strip.style.transform = `translateY(${-extra * CELL.dy}px)`
}
const start: Symbol[][] = [
  ['PINEAPPLE', 'WILD', 'APRICOT'], ['MUSHROOM', 'K', 'BANANA'], ['APRICOT', 'PINEAPPLE', 'MUSHROOM'],
  ['PINEAPPLE', 'WILD', 'K'], ['MUSHROOM', 'COCONUT', 'BANANA'],
]
start.forEach((col, reel) => fill(reel, col))

/** Rolls every reel and stops them one after another on the dealt screen. */
async function roll(grid: Symbol[][]) {
  const spins = still ? 0 : 18
  await Promise.all(grid.map((col, reel) => new Promise<void>((done) => {
    const extra = spins + reel * 4
    // The result on top, random symbols below it; the strip slides down until the result shows.
    const { strip, el } = reels[reel]!
    strip.replaceChildren()
    const items: Symbol[] = [...col, ...Array.from({ length: extra }, () => SYMBOLS[Math.floor(Math.random() * 7)]!)]
    for (const s of items) {
      const img = document.createElement('img')
      img.className = 'sym'
      img.src = src(s)
      img.alt = s.toLowerCase()
      img.dataset.symbol = s
      strip.append(img)
    }
    // Start showing the last three (random), end showing the first three (the result).
    strip.style.transition = 'none'
    strip.style.transform = `translateY(${-(items.length - 3) * CELL.dy}px)`
    el.classList.toggle('rolling', extra > 0)
    void strip.offsetHeight
    const ms = still ? 0 : 900 + reel * 260
    strip.style.transition = `transform ${ms}ms cubic-bezier(0.25, 0.1, 0.15, 1.02)`
    strip.style.transform = 'translateY(0)'
    setTimeout(() => {
      el.classList.remove('rolling')
      // Keep only the result.
      while (strip.children.length > 3) strip.lastElementChild?.remove()
      done()
    }, ms + 30)
  })))
}

function cellCenter(reel: number, row: number): [number, number] {
  return [CELL.x + CELL.dx * reel + 104, CELL.y + CELL.dy * row + 91]
}
function clearWins() {
  linesSvg.replaceChildren()
  for (const img of reelsEl.querySelectorAll<HTMLImageElement>('.sym')) {
    img.classList.remove('lit', 'dim')
    img.src = src(img.dataset.symbol as Symbol)
  }
}
function drawLine(index: number, colour: string) {
  const poly = document.createElementNS('http://www.w3.org/2000/svg', 'polyline')
  const points = PAYLINES[index]!.map((row, reel) => cellCenter(reel, row))
  poly.setAttribute('points', [[CELL.x - 50, points[0]![1]], ...points, [CELL.x + CELL.dx * 4 + 260, points[4]![1]]].map((p) => p.join(',')).join(' '))
  poly.setAttribute('stroke', colour)
  linesSvg.append(poly)
}
function showWins(round: Round) {
  clearWins()
  if (round.lineWins.length === 0) return
  const lit = new Set(round.lineWins.flatMap((w) => w.positions.map(([r, row]) => `${r}-${row}`)))
  reels.forEach(({ strip }, reel) => {
    Array.from(strip.children as HTMLCollectionOf<HTMLImageElement>).forEach((img, row) => {
      const on = lit.has(`${reel}-${row}`)
      img.classList.toggle('lit', on)
      img.classList.toggle('dim', !on)
      if (on) img.src = src(img.dataset.symbol as Symbol, true)
    })
  })
  round.lineWins.forEach((w, i) => drawLine(w.line, LINE_COLOURS[i % LINE_COLOURS.length]!))
}

// Boxes: Lines, Line bet, Total bet, Won, Credits, Spins.
const boxes: HTMLDivElement[] = []
const outputs = BOXES.map((label, i) => {
  const box = document.createElement('div')
  box.className = 'box'
  boxes.push(box)
  const img = document.createElement('img')
  img.src = `${ART}low-scores-${i}.webp`
  img.alt = ''
  const out = document.createElement('output')
  out.setAttribute('aria-label', label)
  box.append(img, out)
  $('boxes').append(box)
  return out
})

// Buttons.
const buttons = Object.fromEntries(
  (Object.keys(BUTTONS) as (keyof typeof BUTTONS)[]).map((key) => {
    const b = document.createElement('button')
    b.type = 'button'
    b.setAttribute('aria-label', LABELS[key])
    const img = document.createElement('img')
    img.src = `${ART}${BUTTON_ART[key]}.webp`
    img.alt = ''
    b.append(img)
    $('buttons').append(b)
    return [key, b]
  }),
) as Record<keyof typeof BUTTONS, HTMLButtonElement>

// Lay the game out for the window: the original landscape design, or the upright one; then scale it to fit.
let upright: boolean | null = null
function fit() {
  const nowUpright = innerHeight > innerWidth
  if (nowUpright !== upright) {
    upright = nowUpright
    document.body.classList.toggle('upright', upright)
    const [width, height] = upright ? [UPRIGHT.width, UPRIGHT.height] : [1920, 1080]
    Object.assign(stage.style, { width: `${width}px`, height: `${height}px` })
    $('scene').style.transform = upright ? `translateX(${UPRIGHT.sceneX}px)` : ''
    for (const key of Object.keys(BUTTONS) as (keyof typeof BUTTONS)[]) {
      place(buttons[key], upright ? UPRIGHT_BUTTONS[key] : BUTTONS[key])
    }
    boxes.forEach((box, i) => place(box, upright ? UPRIGHT_BOXES[i]! : [BOX_X[i]!, 946, 225, 107]))
  }
  // The hint shows on phones held upright, above the game.
  hint.hidden = !(upright && innerWidth <= 700)
  const top = hint.hidden ? 0 : hint.offsetHeight
  const width = upright ? UPRIGHT.width : 1920
  const height = upright ? UPRIGHT.height : 1080
  const scale = Math.min(innerWidth / width, (innerHeight - top) / height)
  const x = (innerWidth - width * scale) / 2
  const y = top + (innerHeight - top - height * scale) / 2
  stage.style.transform = `translate(${x}px, ${y}px) scale(${scale})`
}
addEventListener('resize', fit)
fit()

// State.
let bets: number[] = [10]
let betIndex = 0
let balance = 0
let won = 0
let busy = false
/** The latest win count in progress (HayBet item 17). */
let settling = 0
let autoLeft = 0
let autoCount = AUTO_COUNTS[0]!

function render() {
  const bet = bets[betIndex]!
  outputs[0]!.textContent = String(LINES)
  outputs[1]!.textContent = (bet / LINES).toLocaleString('en-US', { maximumFractionDigits: 2 })
  outputs[2]!.textContent = fishkas(bet)
  outputs[3]!.textContent = fishkas(won)
  outputs[4]!.textContent = fishkas(balance)
  outputs[5]!.textContent = autoLeft > 0 ? String(autoLeft) : `×${autoCount}`
  buttons.auto.classList.toggle('on', autoLeft > 0)
  for (const key of ['minus', 'max', 'betLine', 'spins'] as const) buttons[key].disabled = busy || autoLeft > 0
  buttons.spin.disabled = busy && autoLeft === 0
}
function say(text: string, win = false) {
  message.textContent = text
  message.classList.toggle('win', win)
}

async function play() {
  if (busy) return
  const bet = bets[betIndex]!
  if (balance < bet) {
    autoLeft = 0
    say('Not enough Fishkas for this bet.')
    tellLowBalance()
    render()
    return
  }
  busy = true
  won = 0
  say('')
  clearWins()
  monkey.classList.remove('show')
  render()
  try {
    const result = await spin<Round>(GAME, bet)
    // The bet leaves the credits at once; a win is added only after it has been shown (HayBet item 17).
    balance = result.balance - result.win
    render()
    await roll(result.round.grid)
    won = result.win
    showWins(result.round)
    if (result.win > 0) {
      say(`WIN ${fishkas(result.win)}`, true)
      // A big win: the monkey cheers for a moment (it would cover two reels if it stayed, as in the original art).
      if (result.win >= bet * 10) {
        monkey.classList.add('show')
        setTimeout(() => monkey.classList.remove('show'), 2500)
      }
    }
    // A second after the win is shown the credits count up; the page's header follows (settle). A newer spin wins
    // over an older count still running (autoplay), so consecutive wins never overwrite each other.
    const token = ++settling
    void settleWin(balance, result.balance, (value) => {
      if (token !== settling) return
      balance = value
      render()
    })
  } catch (error) {
    autoLeft = 0
    const e = error as HouseError
    if (typeof e.balance === 'number') balance = e.balance
    say(e.message)
  } finally {
    busy = false
    render()
  }
  if (autoLeft > 0) {
    autoLeft -= 1
    render()
    // After a win, time for the pause and the credits counting up (about 1.8 s) before the next spin.
    if (autoLeft > 0) setTimeout(() => void play(), won > 0 ? 2200 : 500)
  }
}

buttons.spin.addEventListener('click', () => {
  if (autoLeft > 0) {
    autoLeft = 0
    render()
    return
  }
  void play()
})
buttons.minus.addEventListener('click', () => { betIndex = Math.max(0, betIndex - 1); render() })
buttons.betLine.addEventListener('click', () => { betIndex = (betIndex + 1) % bets.length; render() })
buttons.max.addEventListener('click', () => { betIndex = bets.length - 1; render() })
buttons.spins.addEventListener('click', () => {
  autoCount = AUTO_COUNTS[(AUTO_COUNTS.indexOf(autoCount) + 1) % AUTO_COUNTS.length]!
  render()
})
buttons.auto.addEventListener('click', () => {
  if (autoLeft > 0) { autoLeft = 0; render(); return }
  autoLeft = autoCount
  render()
  void play()
})
buttons.lines.addEventListener('click', () => {
  clearWins()
  PAYLINES.forEach((_, i) => drawLine(i, LINE_COLOURS[i % LINE_COLOURS.length]!))
  setTimeout(clearWins, 2600)
})
buttons.info.addEventListener('click', () => {
  overlay.hidden = false
  overlay.innerHTML = `<button class="close" aria-label="Close">×</button><h2>Paytable</h2><div class="pays">${SYMBOLS.map(
    (s) => {
      const [three, four, five] = PAYS[s]
      return `<div class="pay"><img src="${src(s)}" alt="${s.toLowerCase()}"><div>5× <b>${five}</b><br>4× <b>${four}</b><br>3× <b>${three}</b></div></div>`
    },
  ).join('')}</div><p>Pays per line bet, left to right, 3 or more on one of 20 lines. The treasure chest is wild: it stands in for every symbol and pays on its own. Played with Fishkas — play money.</p>`
  overlay.querySelector('button')?.addEventListener('click', () => { overlay.hidden = true })
})
addEventListener('keydown', (e) => {
  if (e.code === 'Space' && !e.repeat && overlay.hidden) {
    e.preventDefault()
    buttons.spin.click()
  }
  if (e.key === 'Escape') overlay.hidden = true
})

openGame(GAME)
  .then((session) => {
    bets = session.bets.length ? session.bets : [10]
    balance = session.balance
    render()
  })
  .catch((error: HouseError) => say(error.message))
render()
