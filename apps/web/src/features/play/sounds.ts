/**
 * Game sounds, synthesized with the Web Audio API: no sound files, nothing to license. Quiet and
 * short, in the club's wood-and-cards spirit. Browsers only allow audio after the player has tapped
 * or pressed a key, so the context is created on the first gesture ({@link unlockSounds}); until
 * then, and wherever audio is unavailable, sounds are silently dropped.
 */

export type Sound = 'move' | 'capture' | 'yourTurn' | 'lowTime' | 'win' | 'draw' | 'loss'
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
  win: (ctx, out, at) => {
    tone(ctx, out, at, { type: 'triangle', freq: 523, peak: 0.55, decay: 0.2 })
    tone(ctx, out, at + 0.1, { type: 'triangle', freq: 659, peak: 0.55, decay: 0.2 })
    tone(ctx, out, at + 0.2, { type: 'triangle', freq: 784, peak: 0.55, decay: 0.45 })
  },
  draw: (ctx, out, at) => {
    tone(ctx, out, at, { freq: 392, peak: 0.5, decay: 0.22 })
    tone(ctx, out, at + 0.16, { freq: 392, peak: 0.5, decay: 0.22 })
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
