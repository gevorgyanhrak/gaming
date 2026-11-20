/**
 * Game sounds, synthesized with the Web Audio API: no sound files, nothing to license. Quiet and
 * short, in the club's wood-and-cards spirit. Browsers only allow audio after the player has tapped
 * or pressed a key, so the context is created on the first gesture ({@link unlockSounds}); until
 * then, and wherever audio is unavailable, sounds are silently dropped.
 */

export type Sound =
  | 'move'
  | 'capture'
  | 'yourTurn'
  | 'lowTime'
  | 'handWin'
  | 'win'
  | 'draw'
  | 'loss'
  // Casino touches outside the games (2026-11-14): a card dealt, chips clinking, a soft button tick, the
  // big-win fanfare and the achievement chime.
  | 'deal'
  | 'chips'
  | 'click'
  | 'fanfare'
  | 'unlock'
  // HayBet (item 8): the app opening, a game starting, a reward collected, a level reached.
  | 'welcome'
  | 'gameStart'
  | 'reward'
  | 'levelUp'
export type Material = 'wood' | 'cards'

const MASTER = 0.25

let context: AudioContext | null = null
let master: GainNode | null = null

/** Creates (or resumes) the audio context; call from inside a user gesture. */
export function unlockSounds() {
  try {
    if (!context) {
      const Context = window.AudioContext as typeof AudioContext | undefined
      if (!Context) return
      context = new Context()
      master = context.createGain()
      master.gain.value = MASTER
      master.connect(context.destination)
    }
    if (context.state === 'suspended') void context.resume().catch(() => undefined)
  } catch {
    context = null
  }
}

let uiOn = false

/** Whether the player has sounds on (set from their preference by useSoundSetting); UI sounds follow it. */
export function setUiSounds(on: boolean) {
  uiOn = on
}

let lastReward = 0

/**
 * A sound outside a game (buttons, chips, celebrations): only when the player has sounds on. The wallet's chip clink
 * stays quiet just after a reward or level-up sound, which already says the Fishkas arrived.
 */
export function playUiSound(sound: Sound) {
  if (!uiOn) return
  const now = Date.now()
  if (sound === 'reward' || sound === 'levelUp') lastReward = now
  else if (sound === 'chips' && now - lastReward < 1500) return
  playSound(sound)
}

/** Plays a sound now, if audio is unlocked and running. */
export function playSound(sound: Sound, material: Material = 'wood') {
  try {
    if (!context || !master || context.state !== 'running') return
    recipes[sound](context, master, context.currentTime, material)
  } catch {
    // Audio is a nicety: never let it break the game.
  }
}

type Recipe = (ctx: AudioContext, out: AudioNode, at: number, material: Material) => void

/** A gain envelope: quick attack to `peak`, exponential decay to silence. */
function envelope(ctx: AudioContext, out: AudioNode, at: number, peak: number, decay: number) {
  const gain = ctx.createGain()
  gain.gain.setValueAtTime(0.0001, at)
  gain.gain.exponentialRampToValueAtTime(peak, at + 0.004)
  gain.gain.exponentialRampToValueAtTime(0.0001, at + decay)
  gain.connect(out)
  return gain
}

function tone(
  ctx: AudioContext,
  out: AudioNode,
  at: number,
  {
    type = 'sine',
    freq,
    to,
    peak,
    decay,
  }: {
    type?: OscillatorType
    freq: number
    to?: number
    peak: number
    decay: number
  },
) {
  const osc = ctx.createOscillator()
  osc.type = type
  osc.frequency.setValueAtTime(freq, at)
  if (to) osc.frequency.exponentialRampToValueAtTime(to, at + 0.04)
  osc.connect(envelope(ctx, out, at, peak, decay))
  osc.start(at)
  osc.stop(at + decay + 0.05)
}

function noise(
  ctx: AudioContext,
  out: AudioNode,
  at: number,
  freq: number,
  peak: number,
  decay: number,
) {
  const buffer = ctx.createBuffer(1, Math.ceil(ctx.sampleRate * decay), ctx.sampleRate)
  const data = buffer.getChannelData(0)
  for (let i = 0; i < data.length; i++) data[i] = Math.random() * 2 - 1
  const source = ctx.createBufferSource()
  source.buffer = buffer
  const band = ctx.createBiquadFilter()
  band.type = 'bandpass'
  band.frequency.value = freq
  band.Q.value = 0.7
  const high = ctx.createBiquadFilter()
  high.type = 'highpass'
  high.frequency.value = 800
  source
    .connect(band)
    .connect(high)
    .connect(envelope(ctx, out, at, peak, decay))
  source.start(at)
}

function move(ctx: AudioContext, out: AudioNode, at: number, material: Material, pitch = 1) {
  if (material === 'cards') {
    noise(ctx, out, at, 3000 * pitch, 0.5, 0.05)
  } else {
    tone(ctx, out, at, { freq: 220 * pitch, to: 120 * pitch, peak: 0.8, decay: 0.06 })
    noise(ctx, out, at, 2000 * pitch, 0.3, 0.008)
  }
}

const recipes: Record<Sound, Recipe> = {
  move: (ctx, out, at, material) => move(ctx, out, at, material),
  capture: (ctx, out, at, material) => {
    move(ctx, out, at, material)
    move(ctx, out, at + 0.07, material, 0.8)
    if (material === 'cards') tone(ctx, out, at + 0.07, { freq: 110, peak: 0.6, decay: 0.08 })
  },
  yourTurn: (ctx, out, at) => {
    for (const [freq, delay] of [
      [659, 0],
      [880, 0.11],
    ] as const) {
      tone(ctx, out, at + delay, { freq, peak: 0.6, decay: 0.3 })
      tone(ctx, out, at + delay, { freq: freq * 2, peak: 0.09, decay: 0.3 })
    }
  },
  lowTime: (ctx, out, at) => {
    tone(ctx, out, at, { type: 'triangle', freq: 523, peak: 0.45, decay: 0.08 })
    tone(ctx, out, at + 0.15, { type: 'triangle', freq: 523, peak: 0.45, decay: 0.08 })
  },
  // Winning a round inside a game (a poker hand): the win triad, quieter and shorter.
  handWin: (ctx, out, at) => {
    tone(ctx, out, at, { type: 'triangle', freq: 523, peak: 0.33, decay: 0.2 })
    tone(ctx, out, at + 0.1, { type: 'triangle', freq: 659, peak: 0.33, decay: 0.2 })
    tone(ctx, out, at + 0.2, { type: 'triangle', freq: 784, peak: 0.33, decay: 0.25 })
  },
  win: (ctx, out, at) => {
    tone(ctx, out, at, { type: 'triangle', freq: 523, peak: 0.55, decay: 0.2 })
    tone(ctx, out, at + 0.1, { type: 'triangle', freq: 659, peak: 0.55, decay: 0.2 })
    tone(ctx, out, at + 0.2, { type: 'triangle', freq: 784, peak: 0.55, decay: 0.45 })
  },
  draw: (ctx, out, at) => {
    tone(ctx, out, at, { freq: 392, peak: 0.5, decay: 0.22 })
    tone(ctx, out, at + 0.16, { freq: 392, peak: 0.5, decay: 0.22 })
  },
  deal: (ctx, out, at) => noiseBurst(ctx, out, at, 2600, 0.35, 0.09),
  chips: (ctx, out, at) => {
    // Three quick clinks, a little apart in pitch.
    ;[0, 0.05, 0.11].forEach((dt, i) => {
      tone(ctx, out, at + dt, { type: 'triangle', freq: 2400 + i * 260, peak: 0.18, decay: 0.07 })
      tone(ctx, out, at + dt, { type: 'sine', freq: 4700 + i * 300, peak: 0.08, decay: 0.05 })
    })
  },
  click: (ctx, out, at) =>
    tone(ctx, out, at, { type: 'triangle', freq: 1250, peak: 0.12, decay: 0.05 }),
  fanfare: (ctx, out, at) => {
    // A rising brass-like arpeggio, then a shimmer.
    ;[523, 659, 784, 1047].forEach((freq, i) => {
      tone(ctx, out, at + i * 0.12, { type: 'sawtooth', freq, peak: 0.16, decay: 0.32 })
      tone(ctx, out, at + i * 0.12, { type: 'triangle', freq, peak: 0.3, decay: 0.36 })
    })
    ;[1568, 2093, 2637].forEach((freq, i) => {
      tone(ctx, out, at + 0.55 + i * 0.07, { type: 'sine', freq, peak: 0.12, decay: 0.5 })
    })
  },
  unlock: (ctx, out, at) => {
    tone(ctx, out, at, { type: 'sine', freq: 1319, peak: 0.3, decay: 0.6 })
    tone(ctx, out, at + 0.09, { type: 'sine', freq: 1976, peak: 0.22, decay: 0.7 })
  },
  welcome: (ctx, out, at) => {
    // A soft lounge chime: a major-seventh chord rolled upwards.
    ;[392, 494, 587, 740].forEach((freq, i) => {
      tone(ctx, out, at + i * 0.08, { type: 'sine', freq, peak: 0.16, decay: 1.1 })
    })
  },
  gameStart: (ctx, out, at) => {
    // A riffle of chips and a bright shimmer: the table is open.
    ;[0, 0.04, 0.08, 0.12, 0.16].forEach((dt, i) => {
      tone(ctx, out, at + dt, { type: 'triangle', freq: 2100 + i * 180, peak: 0.12, decay: 0.06 })
    })
    tone(ctx, out, at + 0.22, { type: 'sine', freq: 1047, peak: 0.2, decay: 0.45 })
    tone(ctx, out, at + 0.3, { type: 'sine', freq: 1568, peak: 0.14, decay: 0.55 })
  },
  reward: (ctx, out, at) => {
    // Coins tumbling into a pile, rising a little.
    for (let i = 0; i < 7; i++) {
      const dt = i * 0.055 + Math.random() * 0.02
      tone(ctx, out, at + dt, {
        type: 'triangle',
        freq: 2000 + i * 170 + Math.random() * 120,
        peak: 0.14,
        decay: 0.09,
      })
    }
    tone(ctx, out, at + 0.42, { type: 'sine', freq: 2093, peak: 0.16, decay: 0.5 })
  },
  levelUp: (ctx, out, at) => {
    // A short rising fanfare ending on a held chord.
    ;[523, 659, 784].forEach((freq, i) => {
      tone(ctx, out, at + i * 0.1, { type: 'triangle', freq, peak: 0.26, decay: 0.28 })
    })
    ;[1047, 1319, 1568].forEach((freq) => {
      tone(ctx, out, at + 0.34, { type: 'sine', freq, peak: 0.14, decay: 1.2 })
    })
  },
  loss: (ctx, out, at) => {
    const low = ctx.createBiquadFilter()
    low.type = 'lowpass'
    low.frequency.value = 1500
    low.connect(out)
    tone(ctx, low, at, { freq: 440, peak: 0.5, decay: 0.35 })
    tone(ctx, low, at + 0.18, { freq: 349, peak: 0.5, decay: 0.35 })
  },
}

/** A short filtered noise burst: a card sliding off the deck. */
function noiseBurst(
  ctx: AudioContext,
  out: AudioNode,
  at: number,
  cutoff: number,
  peak: number,
  decay: number,
) {
  const length = Math.ceil(ctx.sampleRate * decay)
  const buffer = ctx.createBuffer(1, length, ctx.sampleRate)
  const data = buffer.getChannelData(0)
  for (let i = 0; i < length; i++) data[i] = (Math.random() * 2 - 1) * (1 - i / length)
  const source = ctx.createBufferSource()
  source.buffer = buffer
  const band = ctx.createBiquadFilter()
  band.type = 'bandpass'
  band.frequency.value = cutoff
  source.connect(band)
  band.connect(envelope(ctx, out, at, peak, decay))
  source.start(at)
  source.stop(at + decay)
}

/**
 * The casino's background ambience (HayBet item 8): a very quiet lounge pad — soft chords that drift every 12–20 s —
 * with a distant chip clink now and then. Synthesized, never a loop of the same notes. Plays only while the player
 * has sounds on, after a gesture (browser autoplay rules), and is stopped at game tables (`setAmbience(false)`).
 */
const AMBIENCE_GAIN = 0.16 // of the master (0.25): about 4 % of full volume
const CHORDS = [
  [196, 247, 294, 370], // G maj7
  [165, 196, 247, 294], // E m7
  [131, 165, 196, 247], // C maj7
  [147, 185, 220, 262], // D 7
  [175, 220, 262, 330], // F maj7
]
let ambience: { gain: GainNode; timers: number[]; voices: OscillatorNode[] } | null = null

export function setAmbience(on: boolean) {
  if (!on) {
    stopAmbience()
    return
  }
  if (ambience || !context || !master || context.state !== 'running') return
  const ctx = context
  const gain = ctx.createGain()
  gain.gain.value = 0.0001
  const low = ctx.createBiquadFilter()
  low.type = 'lowpass'
  low.frequency.value = 900
  low.connect(gain).connect(master)
  gain.gain.exponentialRampToValueAtTime(AMBIENCE_GAIN, ctx.currentTime + 3)
  const state = { gain, timers: [] as number[], voices: [] as OscillatorNode[] }
  ambience = state

  const playChord = () => {
    if (ambience !== state) return
    const chord = CHORDS[Math.floor(Math.random() * CHORDS.length)]!
    const at = ctx.currentTime
    const length = 12 + Math.random() * 8
    for (const freq of chord) {
      const osc = ctx.createOscillator()
      osc.type = 'triangle'
      osc.frequency.value = freq * (1 + (Math.random() - 0.5) * 0.004)
      const voice = ctx.createGain()
      voice.gain.setValueAtTime(0.0001, at)
      voice.gain.exponentialRampToValueAtTime(0.06, at + 3)
      voice.gain.setValueAtTime(0.06, at + length - 3)
      voice.gain.exponentialRampToValueAtTime(0.0001, at + length + 1)
      osc.connect(voice).connect(low)
      osc.start(at)
      osc.stop(at + length + 1.2)
      state.voices.push(osc)
      osc.onended = () => {
        state.voices = state.voices.filter((v) => v !== osc)
      }
    }
    state.timers.push(window.setTimeout(playChord, (length - 2) * 1000))
  }
  const clink = () => {
    if (ambience !== state) return
    recipes.chips(ctx, gain, ctx.currentTime, 'wood')
    state.timers.push(window.setTimeout(clink, 20_000 + Math.random() * 25_000))
  }
  playChord()
  state.timers.push(window.setTimeout(clink, 8000 + Math.random() * 10_000))
}

function stopAmbience() {
  const state = ambience
  if (!state || !context) return
  ambience = null
  for (const timer of state.timers) window.clearTimeout(timer)
  const at = context.currentTime
  state.gain.gain.cancelScheduledValues(at)
  state.gain.gain.setValueAtTime(Math.max(state.gain.gain.value, 0.0001), at)
  state.gain.gain.exponentialRampToValueAtTime(0.0001, at + 1.2)
  for (const voice of state.voices) {
    try {
      voice.stop(at + 1.3)
    } catch {
      // already stopped
    }
  }
}
