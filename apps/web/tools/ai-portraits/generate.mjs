/**
 * Generates the AI players' portraits (owner 2026-11-17: "natural and diverse" illustrated people, never photos)
 * into public/ai/NNN.svg. Avataaars by Pablo Stanley (free for personal and commercial use), drawn locally with
 * DiceBear 9; only natural looks: real skin and hair colours, calm faces, everyday and smart clothes, glasses,
 * beards, grey hair for older faces. Each portrait's traits come from its number, so a rerun gives the same files.
 *
 *   npm i --no-save @dicebear/core@9 @dicebear/collection@9 && node tools/ai-portraits/generate.mjs [count] [outDir]
 */
import { createAvatar } from '@dicebear/core'
import { avataaars } from '@dicebear/collection'
import { mkdirSync, writeFileSync } from 'node:fs'

const COUNT = Number(process.argv[2] ?? 200)
const OUT = process.argv[3] ?? new URL('../../public/ai/', import.meta.url).pathname

// A small seeded generator: portrait n always gets the same traits.
function random(seed) {
  let s = seed * 2654435761 + 1013904223
  return () => {
    s = Math.imul(s ^ (s >>> 15), 2246822519) + 0x6d2b79f5
    s ^= s >>> 13
    return ((s >>> 0) % 100000) / 100000
  }
}
const one = (r, list) => list[Math.floor(r() * list.length)]

const SHORT = [
  'shortFlat',
  'shortRound',
  'shortWaved',
  'shortCurly',
  'theCaesar',
  'theCaesarAndSidePart',
  'sides',
  'shavedSides',
  'frizzle',
  'shaggy',
  'dreads01',
  'fro',
]
const LONG = [
  'bob',
  'bun',
  'curly',
  'curvy',
  'longButNotTooLong',
  'miaWallace',
  'straight01',
  'straight02',
  'straightAndStrand',
  'bigHair',
  'froBand',
  'fro',
]
const COVERED = ['hijab', 'turban', 'hat']
const HAIR = ['2c1b18', '4a312c', '724133', 'a55728', 'b58143', 'd6b370', 'c93305']
const GREY = ['e8e1e1', 'ecdcbf']
const SKIN = ['ffdbb4', 'edb98a', 'd08b5b', 'ae5d29', '614335']
const CLOTHES = [
  'blazerAndShirt',
  'blazerAndSweater',
  'collarAndSweater',
  'shirtCrewNeck',
  'shirtVNeck',
  'shirtScoopNeck',
  'hoodie',
]
const CLOTH_COLOUR = [
  '262e33',
  '25557c',
  '3c4f5c',
  '929598',
  'e6e6e6',
  '5199e4',
  'ff5c5c',
  'ffffff',
  '65c9ff',
]
const HAT_COLOUR = ['262e33', '25557c', '3c4f5c', '929598', '5199e4', 'e6e6e6']
const BACK = [
  '2b3a55',
  '3d2b4f',
  '1f4a45',
  '4f2f2b',
  '2f3f2a',
  '4a3a22',
  '25324a',
  '3a2a3a',
  '1d3b2f',
  '44302a',
]

mkdirSync(OUT, { recursive: true })
for (let n = 0; n < COUNT; n++) {
  const r = random(n + 1)
  const covered = r() < 0.07
  const long = !covered && r() < 0.48
  const older = r() < 0.22
  const top = covered ? one(r, COVERED) : one(r, long ? LONG : SHORT)
  const beard = !covered && !long && r() < (older ? 0.6 : 0.35)
  const glasses = r() < (older ? 0.55 : 0.22)
  const svg = createAvatar(avataaars, {
    seed: `ai-${n}`,
    top: [top],
    hairColor: [older ? one(r, GREY) : one(r, HAIR)],
    hatColor: [one(r, HAT_COLOUR)],
    skinColor: [one(r, SKIN)],
    eyes: [one(r, ['default', 'default', 'happy', 'side', 'squint', 'wink'])],
    eyebrows: [
      one(r, [
        'defaultNatural',
        'defaultNatural',
        'flatNatural',
        'raisedExcitedNatural',
        'upDownNatural',
      ]),
    ],
    mouth: [one(r, ['default', 'smile', 'smile', 'serious', 'twinkle'])],
    clothing: [one(r, CLOTHES)],
    clothesColor: [one(r, CLOTH_COLOUR)],
    accessories: [one(r, ['prescription01', 'prescription02', 'round', 'wayfarers', 'kurt'])],
    accessoriesProbability: glasses ? 100 : 0,
    accessoriesColor: [one(r, ['262e33', '3c4f5c', '929598'])],
    facialHair: [
      one(r, ['beardLight', 'beardMedium', 'beardMajestic', 'moustacheFancy', 'moustacheMagnum']),
    ],
    facialHairProbability: beard ? 100 : 0,
    facialHairColor: [older ? one(r, GREY) : one(r, HAIR)],
    backgroundColor: [one(r, BACK)],
  })
    .toString()
    // The licence lives in CREDITS.md next to the files; the per-file metadata only adds weight.
    .replace(/<metadata[\s\S]*?<\/metadata>/, '')
  writeFileSync(`${OUT}/${String(n).padStart(3, '0')}.svg`, svg)
}
console.log(`${COUNT} portraits in ${OUT}`)
