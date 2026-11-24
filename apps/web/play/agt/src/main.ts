/**
 * The shared client of the AGT-style house slots (owner, 2026-11-24). Every game is drawn from the layout the server
 * sends with the session (grid, lines, symbols, pay tables — House\Agt\AgtSlot::layout()), and every round comes
 * from the server: the browser only shows it. A game's own page (play/<slug>/index.html) only says which game it is
 * (data-game) and its colours (data-theme).
 */
import i18n from '@/shared/i18n'
import { HouseError, fishkas, openGame, settleWin, spin, tellLowBalance } from '../../shared/house'
import { symbolSvg } from './symbols'

type Layout = {
  title: string
  cols: number
  rows: number
  lines: number[][]
  symbols: string[]
  line_pay: number[][]
  scatter: string | null
  scatter_pay: number[]
  wild: string | null
  wild_mode: 'none' | 'substitute' | 'expanding' | 'line_wild'
}
type LineWin = { line: number; symbol: string; count: number; amount: number; positions: [number, number][] }
type Round = {
  grid: string[][]
  lineWins: LineWin[]
  scatterWin: { symbol: string; count: number; amount: number; positions: [number, number][] } | null
  expanded: number[]
  totalWin: number
}

const root = document.getElementById('app')!
const GAME = root.dataset.game ?? ''
const WILD_STYLE = root.dataset.wild
const t = (key: string, options?: Record<string, unknown>) => i18n.t(`agt.${key}`, options)
const still = matchMedia('(prefers-reduced-motion: reduce)').matches
const LINE_COLOURS = ['#ffe14d', '#ff5c8a', '#4dd2ff', '#7dff6b', '#ff9f43', '#c084fc', '#f87171', '#34d399', '#fbbf24', '#60a5fa']

let layout: Layout
let bets: number[] = [10]
let betIndex = 0
let balance = 0
let won = 0
let busy = false
let autoLeft = 0
let settling = 0

root.innerHTML = `
  <div id="stage">
    <header class="top"><h1 id="title"></h1><p id="lines-label"></p></header>
    <div id="board"><div id="reels"></div><svg id="paylines" aria-hidden="true"></svg></div>
    <p id="message" role="status" aria-live="polite"></p>
    <footer class="panel">
      <div class="box"><span>${t('credits')}</span><output id="credits">—</output></div>
      <div class="box bet">
        <button type="button" id="bet-down" aria-label="${t('betDown')}">−</button>
        <div><span>${t('bet')}</span><output id="bet">—</output></div>
        <button type="button" id="bet-up" aria-label="${t('betUp')}">+</button>
      </div>
      <div class="box"><span>${t('win')}</span><output id="win">0</output></div>
      <div class="buttons">
        <button type="button" id="info" class="round">${t('info')}</button>
        <button type="button" id="max" class="round">${t('maxBet')}</button>
        <button type="button" id="auto" class="round">${t('auto')}</button>
        <button type="button" id="start" class="start">${t('start')}</button>
      </div>
    </footer>
    <div id="overlay" role="dialog" aria-modal="true" aria-labelledby="pay-title" hidden></div>
  </div>
  <p id="rotate">${i18n.t('slots.rotate')}</p>`

const $ = <T extends Element>(selector: string) => root.querySelector(selector) as T
const reelsEl = $<HTMLDivElement>('#reels')
const linesSvg = $<SVGSVGElement>('#paylines')
const message = $<HTMLParagraphElement>('#message')
const overlay = $<HTMLDivElement>('#overlay')
const stage = $<HTMLDivElement>('#stage')

// The stage keeps its design size (16:9, or 9:16 held upright) and is scaled to the window.
function fit() {
  const upright = innerHeight > innerWidth * 1.15
  stage.classList.toggle('upright', upright)
  const [w, h] = upright ? [720, 1280] : [1280, 720]
  const scale = Math.min(innerWidth / w, innerHeight / h)
  stage.style.transform = `translate(-50%, -50%) scale(${scale})`
}
addEventListener('resize', fit)
fit()

let cellId = 0
function cell(symbol: string): HTMLDivElement {
  const el = document.createElement('div')
  el.className = 'cell'
  el.dataset.symbol = symbol
  el.innerHTML = symbolSvg(symbol, `s${cellId++}`, WILD_STYLE)
  return el
}

/** Draws a column: `symbols` top to bottom (the visible rows at the end of a spin). */
function drawReel(reel: number, symbols: string[]) {
  const column = reelsEl.children[reel] as HTMLDivElement
  const strip = column.firstElementChild as HTMLDivElement
  strip.replaceChildren(...symbols.map(cell))
  strip.style.transition = 'none'
  strip.style.transform = 'translateY(0)'
}

function randomSymbols(n: number): string[] {
  const pool = layout.symbols.filter((s) => s !== layout.wild || layout.wild_mode !== 'expanding')
  return Array.from({ length: n }, () => pool[Math.floor(Math.random() * pool.length)]!)
}

/** Spins every reel and stops them left to right on the server's screen. */
async function roll(grid: string[][]) {
  // One row's height in layout pixels (the stage's scaling doesn't change it).
  const rowPx = (reelsEl.firstElementChild as HTMLDivElement).clientHeight / layout.rows
  await Promise.all(
    grid.map(
      (column, reel) =>
        new Promise<void>((done) => {
          const extra = still ? 0 : 14 + reel * 5
          const strip = (reelsEl.children[reel] as HTMLDivElement).firstElementChild as HTMLDivElement
          // The result on top, random symbols below: the strip slides down until the result shows.
          strip.replaceChildren(...[...column, ...randomSymbols(extra)].map(cell))
          strip.style.transition = 'none'
          strip.style.transform = `translateY(-${extra * rowPx}px)`
          strip.parentElement!.classList.toggle('rolling', extra > 0)
          void strip.offsetHeight
          const ms = still ? 0 : 700 + reel * 220
          strip.style.transition = `transform ${ms}ms cubic-bezier(.2,.1,.15,1.04)`
          strip.style.transform = 'translateY(0)'
          setTimeout(() => {
            strip.parentElement!.classList.remove('rolling')
            while (strip.children.length > layout.rows) strip.lastElementChild?.remove()
            done()
          }, ms + 30)
        }),
    ),
  )
}

function cellAt(reel: number, row: number): HTMLDivElement | null {
  const strip = (reelsEl.children[reel] as HTMLDivElement | undefined)?.firstElementChild
  return (strip?.children[row] as HTMLDivElement | undefined) ?? null
}

function clearWins() {
  linesSvg.replaceChildren()
  for (const el of reelsEl.querySelectorAll('.cell')) el.classList.remove('lit', 'dim')
}

function drawLine(index: number, colour: string) {
  const rows = layout.lines[index]!
  const w = 100 / layout.cols
  const h = 100 / layout.rows
  const points = rows.map((row, reel) => `${(reel + 0.5) * w},${(row + 0.5) * h}`).join(' ')
  const poly = document.createElementNS('http://www.w3.org/2000/svg', 'polyline')
  poly.setAttribute('points', points)
  poly.setAttribute('stroke', colour)
  linesSvg.append(poly)
}

/** The expanding wild: the reel turns wild from top to bottom before the wins are counted. */
async function expand(reels: number[]) {
  for (const reel of reels) {
    for (let row = 0; row < layout.rows; row++) {
      const el = cellAt(reel, row)
      if (el && el.dataset.symbol !== layout.wild) {
        el.replaceWith(Object.assign(cell(layout.wild!), { className: 'cell grown' }))
      }
    }
    if (!still) await new Promise((r) => setTimeout(r, 220))
  }
}

function showWins(round: Round) {
  clearWins()
  const lit = new Set<string>()
  round.lineWins.forEach((win, i) => {
    drawLine(win.line, LINE_COLOURS[i % LINE_COLOURS.length]!)
    for (const [reel, row] of win.positions) lit.add(`${reel}:${row}`)
  })
  for (const [reel, row] of round.scatterWin?.positions ?? []) lit.add(`${reel}:${row}`)
  if (lit.size === 0) return
  for (let reel = 0; reel < layout.cols; reel++) {
    for (let row = 0; row < layout.rows; row++) {
      cellAt(reel, row)?.classList.add(lit.has(`${reel}:${row}`) ? 'lit' : 'dim')
    }
  }
}

function render() {
  const bet = bets[betIndex]!
  $('#credits').textContent = fishkas(balance)
  $('#bet').textContent = fishkas(bet)
  $('#win').textContent = fishkas(won)
  $('#auto').classList.toggle('on', autoLeft > 0)
  $<HTMLButtonElement>('#auto').textContent = autoLeft > 0 ? String(autoLeft) : t('auto')
  for (const id of ['#bet-down', '#bet-up', '#max', '#info']) $<HTMLButtonElement>(id).disabled = busy || autoLeft > 0
  $<HTMLButtonElement>('#start').disabled = busy && autoLeft === 0
  $<HTMLButtonElement>('#start').textContent = autoLeft > 0 ? t('stop') : t('start')
}
const say = (text: string, win = false) => {
  message.textContent = text
  message.classList.toggle('win', win)
}

async function play() {
  if (busy) return
  const bet = bets[betIndex]!
  if (balance < bet) {
    autoLeft = 0
    if (!tellLowBalance()) say(t('lowBalance'))
    render()
    return
  }
  busy = true
  won = 0
  say('')
  clearWins()
  render()
  try {
    const dealt = await spin<Round>(GAME, bet)
    // The bet leaves the credits at once; a win is added after it has been shown (HayBet item 17).
    balance = dealt.balance - dealt.win
    render()
    await roll(dealt.round.grid)
    if (dealt.round.expanded.length) await expand(dealt.round.expanded)
    showWins(dealt.round)
    won = dealt.win
    if (dealt.win > 0) say(t('won', { amount: fishkas(dealt.win) }), true)
    render()
    const token = ++settling
    await settleWin(balance, dealt.balance, (value) => {
      if (token !== settling) return
      balance = value
      render()
    })
  } catch (error) {
    autoLeft = 0
    const failed = error as HouseError
    if (typeof failed.balance === 'number') balance = failed.balance
    say(failed.message)
  } finally {
    busy = false
    render()
  }
  if (autoLeft > 0) {
    autoLeft -= 1
    render()
    if (autoLeft > 0) setTimeout(() => void play(), won > 0 ? 900 : 350)
  }
}

function payTable() {
  const rows = layout.symbols
    .map((name, i) => ({ name, pays: layout.line_pay[i] ?? [] }))
    .filter(({ pays, name }) => pays.some((p) => p > 0) || name === layout.scatter || name === layout.wild)
  const line = (pays: number[]) =>
    pays
      .map((p, i) => (p > 0 ? `<li><b>${i + 1}×</b> ${p}</li>` : ''))
      .reverse()
      .join('')
  overlay.innerHTML = `
    <button type="button" class="close" aria-label="${t('close')}">×</button>
    <h2 id="pay-title">${layout.title}</h2>
    <div class="pays">${rows
      .map(({ name, pays }) => {
        const special =
          name === layout.scatter
            ? `<p>${t('scatterRule')}</p><ul>${line(layout.scatter_pay)}</ul>`
            : name === layout.wild
              ? `<p>${t(layout.wild_mode === 'expanding' ? 'wildExpanding' : 'wildSubstitute')}</p>${pays.some((p) => p > 0) ? `<ul>${line(pays)}</ul>` : ''}`
              : `<ul>${line(pays)}</ul>`
        return `<div class="pay"><span class="icon">${symbolSvg(name, `p-${name}`, WILD_STYLE)}</span>${special}</div>`
      })
      .join('')}</div>
    <p class="rules">${t('rules', { lines: layout.lines.length })}</p>`
  overlay.hidden = false
  overlay.querySelector<HTMLButtonElement>('.close')?.focus()
  overlay.querySelector('.close')?.addEventListener('click', () => {
    overlay.hidden = true
  })
}

function bindButtons() {
  $('#start').addEventListener('click', () => {
    if (autoLeft > 0) {
      autoLeft = 0
      render()
      return
    }
    void play()
  })
  $('#bet-down').addEventListener('click', () => {
    betIndex = Math.max(0, betIndex - 1)
    render()
  })
  $('#bet-up').addEventListener('click', () => {
    betIndex = Math.min(bets.length - 1, betIndex + 1)
    render()
  })
  $('#max').addEventListener('click', () => {
    betIndex = bets.length - 1
    render()
  })
  $('#auto').addEventListener('click', () => {
    if (autoLeft > 0) {
      autoLeft = 0
      render()
      return
    }
    autoLeft = 10
    render()
    void play()
  })
  $('#info').addEventListener('click', payTable)
  addEventListener('keydown', (event) => {
    if (event.key === 'Escape') overlay.hidden = true
    if (event.code === 'Space' && !event.repeat && overlay.hidden) {
      event.preventDefault()
      $<HTMLButtonElement>('#start').click()
    }
  })
}

async function boot() {
  try {
    const session = (await openGame(GAME)) as Awaited<ReturnType<typeof openGame>> & { layout?: Layout }
    if (!session.layout) throw new HouseError(t('unavailable'))
    layout = session.layout
    bets = session.bets.length ? session.bets : [10]
    balance = session.balance
  } catch (error) {
    say((error as Error).message)
    return
  }
  document.title = layout.title
  $('#title').textContent = layout.title
  $('#lines-label').textContent = t('lines', { count: layout.lines.length })
  stage.style.setProperty('--cols', String(layout.cols))
  stage.style.setProperty('--rows', String(layout.rows))
  linesSvg.setAttribute('viewBox', '0 0 100 100')
  linesSvg.setAttribute('preserveAspectRatio', 'none')
  for (let reel = 0; reel < layout.cols; reel++) {
    const column = document.createElement('div')
    column.className = 'reel'
    column.append(Object.assign(document.createElement('div'), { className: 'strip' }))
    reelsEl.append(column)
  }
  // A cell's height in layout pixels: the reel's height shared by its rows (set once the grid exists).
  const sizeCells = () =>
    reelsEl.style.setProperty('--cell', `${(reelsEl.firstElementChild as HTMLDivElement).clientHeight / layout.rows}px`)
  sizeCells()
  addEventListener('resize', sizeCells)
  for (let reel = 0; reel < layout.cols; reel++) drawReel(reel, randomSymbols(layout.rows))
  bindButtons()
  render()
}

void boot()
