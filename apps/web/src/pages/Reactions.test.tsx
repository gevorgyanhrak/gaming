import { act, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import type { Reaction } from '@/features/reactions/api'
import {
  configFixture,
  roomFixture,
  roomStateFixture,
  signedInAs,
  userFixture,
} from '@/test/handlers'
import { emit } from '@/test/realtime'
import { renderRoute } from '@/test/render'
import { server } from '@/test/server'

// Alice (you, seat 0) and Bob (seat 1), two people at a table.
const room = roomFixture({
  participants: [
    { ...roomFixture().participants[0]!, name: 'alice' },
    {
      ...roomFixture().participants[1]!,
      name: 'Bob',
      username: 'bob',
      is_bot: false,
    },
  ],
})

function reaction(overrides: Partial<Reaction> = {}): Reaction {
  return {
    id: 'r-1',
    seq: 1,
    room: 'room-1',
    from_seat: 1,
    to_seat: 0,
    kind: 'emoji',
    value: '😂',
    sent_at: '2026-11-02T10:00:00+00:00',
    ...overrides,
  }
}

/** Every flight started: its text and its first and last keyframes. */
let flights: { text: string; frames: Keyframe[] }[] = []

/** Where the seats are: jsdom lays nothing out. Your seat (0) is low on the screen, the opponent's high. */
let seatTop: Record<string, number> = {}

function rect(top: number): DOMRect {
  const r = { top, bottom: top + 40, left: 480, right: 520, width: 40, height: 40, x: 480, y: top }
  return { ...r, toJSON: () => r }
}

beforeEach(() => {
  flights = []
  seatTop = { '0': 600, '1': 100 }
  vi.spyOn(Element.prototype, 'getBoundingClientRect').mockImplementation(function (this: Element) {
    const seat = this instanceof HTMLElement ? this.dataset.seatAnchor : undefined
    return seat === undefined ? rect(0) : rect(seatTop[seat] ?? 0)
  })
  Element.prototype.animate = vi.fn(function (this: Element, frames: Keyframe[]) {
    flights.push({ text: this.textContent ?? '', frames })
    return {
      finished: new Promise(() => undefined),
      cancel: () => undefined,
    } as unknown as Animation
  })
})

afterEach(() => {
  vi.restoreAllMocks()
  // @ts-expect-error jsdom has no Web Animations; the stub is per test.
  delete Element.prototype.animate
})

/** The vertical position of a flight's keyframe ("translate(Xpx, Ypx) …"). */
const yOf = (frame: Keyframe | undefined) =>
  Number(/translate\([^,]+,\s*(-?[\d.]+)px\)/.exec(String(frame?.transform))?.[1])

function serve() {
  const sent: unknown[] = []
  let seq = 0
  server.use(
    signedInAs(userFixture({ id: 1, username: 'alice' })),
    http.get('*/api/v1/rooms/room-1', () => HttpResponse.json({ data: room })),
    http.get('*/api/v1/rooms/room-1/state', () => HttpResponse.json({ data: roomStateFixture() })),
    http.post('*/api/v1/rooms/room-1/reactions', async ({ request }) => {
      const body = (await request.json()) as Record<string, unknown>
      sent.push(body)
      seq++
      return HttpResponse.json(
        {
          data: reaction({
            id: `mine-${seq}`,
            seq: 100 + seq,
            from_seat: 0,
            to_seat: body.to_seat as number | null,
            kind: body.kind as string,
            value: body.value as string,
          }),
        },
        { status: 201 },
      )
    }),
  )
  return sent
}

describe('Emojis and quick messages', () => {
  it('sends an emoji to the opponent; it flies from your seat towards theirs', async () => {
    const sent = serve()
    renderRoute('/rooms/room-1')

    await userEvent.click(await screen.findByRole('button', { name: 'Send an emoji' }))
    const picker = await screen.findByRole('dialog', { name: 'Emojis and messages' })
    expect(within(picker).getByRole('button', { name: 'Bob' })).toHaveAttribute(
      'aria-pressed',
      'true',
    )
    await userEvent.click(within(picker).getByRole('button', { name: '❤️ heart' }))

    await waitFor(() => expect(sent).toHaveLength(1))
    expect(sent[0]).toMatchObject({ kind: 'emoji', value: '❤️', to_seat: 1 })
    expect(typeof (sent[0] as { client_id: unknown }).client_id).toBe('string')
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()

    const log = screen.getByRole('log', { name: 'Table chat' })
    expect(await within(log).findByText('❤️')).toBeInTheDocument()
    expect(within(log).getByText(/→ Bob/)).toBeInTheDocument()

    // You are at the bottom, the other player at the top: the heart travels up.
    await waitFor(() => expect(flights.map((f) => f.text)).toContain('❤️'))
    const heart = flights.find((f) => f.text === '❤️')!
    expect(yOf(heart.frames.at(-1))).toBeLessThan(yOf(heart.frames[0]))
  })

  it('shows what the other player sends, coming the other way, once', async () => {
    serve()
    renderRoute('/rooms/room-1')
    const log = await screen.findByRole('log', { name: 'Table chat' })

    act(() => emit('user.1', 'room.reaction', reaction()))
    act(() => emit('user.1', 'room.reaction', reaction())) // the same event twice

    expect(await within(log).findAllByText('😂')).toHaveLength(1)
    expect(within(log).getByText('Bob')).toBeInTheDocument()
    await waitFor(() => expect(flights.filter((f) => f.text === '😂')).toHaveLength(1))
    const laugh = flights.find((f) => f.text === '😂')!
    expect(yOf(laugh.frames.at(-1))).toBeGreaterThan(yOf(laugh.frames[0]))

    // Another room's reaction is not for this page.
    act(() => emit('user.1', 'room.reaction', reaction({ id: 'r-2', seq: 2, room: 'room-9' })))
    expect(within(log).getAllByText('😂')).toHaveLength(1)
  })

  it('draws nothing for a seat scrolled out of view, but the chat lists it', async () => {
    serve()
    seatTop['0'] = 2000 // your seat is scrolled out of view
    renderRoute('/rooms/room-1')
    const log = await screen.findByRole('log', { name: 'Table chat' })

    act(() => emit('user.1', 'room.reaction', reaction({ value: '🔥' })))

    expect(await within(log).findByText('🔥')).toBeInTheDocument()
    expect(flights).toHaveLength(0)
  })

  it('shows several reactions arriving at once', async () => {
    serve()
    renderRoute('/rooms/room-1')
    const log = await screen.findByRole('log', { name: 'Table chat' })

    act(() => {
      emit('user.1', 'room.reaction', reaction({ id: 'a', seq: 1, value: '🔥' }))
      emit('user.1', 'room.reaction', reaction({ id: 'b', seq: 2, value: '👏' }))
      emit(
        'user.1',
        'room.reaction',
        reaction({ id: 'c', seq: 3, kind: 'message', value: 'well_played' }),
      )
    })

    expect(await within(log).findByText('🔥')).toBeInTheDocument()
    expect(within(log).getByText('👏')).toBeInTheDocument()
    expect(within(log).getByText('Well played!')).toBeInTheDocument()
    await waitFor(() => expect(flights).toHaveLength(3))
  })

  it('sends a quick message with one tap, and finds emojis by name in any language', async () => {
    const sent = serve()
    renderRoute('/rooms/room-1')

    await userEvent.click(await screen.findByRole('button', { name: 'Table chat' }))
    await userEvent.click(screen.getByRole('button', { name: 'GG!' }))
    await waitFor(() => expect(sent).toHaveLength(1))
    expect(sent[0]).toMatchObject({ kind: 'message', value: 'gg', to_seat: 1 })
    expect(await within(screen.getByRole('log')).findByText('GG!')).toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: /Messages/ }))
    const picker = await screen.findByRole('dialog')
    expect(within(picker).getByRole('tab', { name: 'Quick messages' })).toHaveAttribute(
      'aria-selected',
      'true',
    )
    await userEvent.click(within(picker).getByRole('tab', { name: 'Emojis' }))
    await userEvent.type(within(picker).getByRole('searchbox', { name: 'Search emojis' }), 'сердце')
    expect(within(picker).getByRole('button', { name: '❤️ heart' })).toBeInTheDocument()
    expect(within(picker).queryByRole('button', { name: /😂/ })).not.toBeInTheDocument()
  })

  it('sends a double-clicked quick message once', async () => {
    const sent = serve()
    renderRoute('/rooms/room-1')

    await userEvent.click(await screen.findByRole('button', { name: 'Table chat' }))
    await userEvent.dblClick(screen.getByRole('button', { name: 'GG!' }))

    expect(await within(screen.getByRole('log')).findByText('GG!')).toBeInTheDocument()
    expect(sent).toHaveLength(1)
  })

  it('finds emojis by a plural word too', async () => {
    serve()
    renderRoute('/rooms/room-1')

    await userEvent.click(await screen.findByRole('button', { name: 'Send an emoji' }))
    await userEvent.type(await screen.findByRole('searchbox', { name: 'Search emojis' }), 'hearts')

    expect(
      within(screen.getByRole('dialog')).getByRole('button', { name: '❤️ heart' }),
    ).toBeInTheDocument()
  })

  it('retries a send the server could not take, without sending it twice', async () => {
    const ids: unknown[] = []
    serve()
    server.use(
      http.post('*/api/v1/rooms/room-1/reactions', async ({ request }) => {
        const body = (await request.json()) as Record<string, unknown>
        ids.push(body.client_id)
        return ids.length === 1
          ? new HttpResponse(null, { status: 502 })
          : HttpResponse.json(
              {
                data: reaction({
                  id: 'mine',
                  seq: 9,
                  from_seat: 0,
                  to_seat: 1,
                  kind: 'message',
                  value: 'gg',
                }),
              },
              { status: 201 },
            )
      }),
    )
    renderRoute('/rooms/room-1')

    await userEvent.click(await screen.findByRole('button', { name: 'Table chat' }))
    await userEvent.click(screen.getByRole('button', { name: 'GG!' }))

    expect(await within(screen.getByRole('log')).findByText('GG!')).toBeInTheDocument()
    expect(ids).toHaveLength(2)
    expect(ids[1]).toBe(ids[0])
    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
  })

  it('shows the table’s earlier messages without replaying them', async () => {
    serve()
    server.use(
      http.get('*/api/v1/rooms/room-1/reactions', () =>
        HttpResponse.json({ data: [reaction({ kind: 'message', value: 'good_luck' })] }),
      ),
    )
    renderRoute('/rooms/room-1')

    expect(
      await within(await screen.findByRole('log')).findByText('Good luck!'),
    ).toBeInTheDocument()
    expect(flights).toHaveLength(0)
  })

  it('is not offered when an administrator switched it off', async () => {
    serve()
    server.use(
      http.get('*/api/v1/config', () =>
        HttpResponse.json({
          data: { ...configFixture, play: { ...configFixture.play, reactions: false } },
        }),
      ),
    )
    renderRoute('/rooms/room-1')

    await screen.findByRole('heading', { name: 'Tic-tac-toe' })
    expect(screen.queryByRole('log', { name: 'Table chat' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Send an emoji' })).not.toBeInTheDocument()
  })
})
