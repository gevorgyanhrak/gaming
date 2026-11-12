<?php

namespace App\Games\Blot\Bot;

use App\Games\Blot\Engine\BlotCards;

/**
 * What a seat knows about the deal, from its own view only (its cards and what the whole table saw): every
 * card played this deal, cards other players are known to hold (shown in a declaration, or the other half of
 * a blot once its first card fell), and what their plays revealed under the rules of play — a player who
 * didn't follow suit has none of it; one who had to trump and didn't has no trumps; one who had to beat a
 * trump and couldn't has none higher. Never looks at anyone's hidden cards.
 */
final class CardMemory
{
    /** @var list<string> */
    public readonly array $hand;

    /** @var list<string> every card played this deal, the current trick included */
    public readonly array $played;

    /** @var array<int, list<string>> cards a seat still holds that everyone knows of */
    public readonly array $known;

    /** @var array<int, array<string, true>> suits a seat has none of */
    public readonly array $voids;

    /** @var array<int, int> per seat, the strongest trump it can still hold (BlotCards::strength; 999 = any) */
    public readonly array $trumpCap;

    public readonly int $seat;

    public readonly ?string $trump;

    /**
     * @param  array<string, mixed>  $view  a seat view of a deal in play (or in the bazar: nothing played yet)
     */
    public function __construct(array $view)
    {
        $this->seat = (int) $view['seat'];
        $this->hand = array_values($view['hand']);
        $this->trump = $view['phase'] === 'playing' || $view['phase'] === 'over' ? ($view['trump'] ?? null) : null;

        $tricks = $view['history'] ?? [];
        if (($view['trick'] ?? []) !== []) {
            $tricks[] = ['cards' => $view['trick'], 'winner' => null];
        }

        $played = [];
        $voids = [[], [], [], []];
        $cap = [999, 999, 999, 999];
        foreach ($tricks as $trick) {
            $cards = [];
            foreach ($trick['cards'] as $i => $play) {
                $who = (int) $play['seat'];
                $card = (string) $play['card'];
                if ($i > 0 && $who !== $this->seat) {
                    $this->learn($cards, $who, $card, $voids, $cap);
                }
                $cards[] = ['seat' => $who, 'card' => $card];
                $played[] = $card;
            }
        }
        $this->played = $played;
        $this->voids = $voids;
        $this->trumpCap = $cap;

        $known = [[], [], [], []];
        foreach ($view['declared'] ?? [] as $who => $list) {
            if ((int) $who === $this->seat) {
                continue;
            }
            foreach ($list as $declaration) {
                foreach ($declaration['cards'] as $card) {
                    if (! in_array($card, $played, true) && ! in_array($card, $known[(int) $who], true)) {
                        $known[(int) $who][] = $card;
                    }
                }
            }
        }
        // Blot was called: the holder still has whichever of the trump king and queen hasn't fallen.
        $holder = $view['belote'] ?? null;
        if ($holder !== null && (int) $holder !== $this->seat && $this->trump !== null) {
            foreach (['K'.$this->trump, 'Q'.$this->trump] as $card) {
                if (! in_array($card, $played, true) && ! in_array($card, $known[(int) $holder], true)) {
                    $known[(int) $holder][] = $card;
                }
            }
        }
        $this->known = $known;

        // Blot is called by itself as its holder plays the first of the trump king and queen: whoever played
        // one of them without it being their blot doesn't hold the other (research: the Belot engine).
        $missing = [[], [], [], []];
        if ($this->trump !== null) {
            foreach ($tricks as $trick) {
                foreach ($trick['cards'] as $play) {
                    $card = (string) $play['card'];
                    $who = (int) $play['seat'];
                    if ($who !== $this->seat && ($card === 'K'.$this->trump || $card === 'Q'.$this->trump) && $holder !== $who) {
                        $missing[$who][] = $card === 'K'.$this->trump ? 'Q'.$this->trump : 'K'.$this->trump;
                    }
                }
            }
        }
        $this->missing = $missing;
    }

    /** @var array<int, list<string>> cards a seat is known not to hold (beyond voids and the trump cap) */
    public readonly array $missing;

    /**
     * Cards nobody has shown or played that this seat doesn't hold: the ones the other hands share.
     *
     * @return list<string>
     */
    public function unseen(): array
    {
        $known = array_merge(...$this->known);

        return array_values(array_filter(self::deck(), fn (string $c) => ! in_array($c, $this->hand, true)
            && ! in_array($c, $this->played, true) && ! in_array($c, $known, true)));
    }

    /** Whether $seat may still hold $card, as far as this seat can tell. */
    public function mayHold(int $seat, string $card): bool
    {
        if ($seat === $this->seat) {
            return in_array($card, $this->hand, true);
        }
        if (in_array($card, $this->played, true)) {
            return false;
        }
        foreach ($this->known as $who => $cards) {
            if (in_array($card, $cards, true)) {
                return $who === $seat;
            }
        }
        if (isset($this->voids[$seat][$card[1]]) || in_array($card, $this->missing[$seat], true)) {
            return false;
        }

        return ! ($this->trump !== null && $card[1] === $this->trump && BlotCards::strength($card, $this->trump, $this->trump) > $this->trumpCap[$seat]);
    }

    /** The highest trump still out (in no one's view played), or null. */
    public function masterTrump(): ?string
    {
        if ($this->trump === null) {
            return null;
        }
        foreach (str_split('J9ATKQ87') as $rank) {
            if (! in_array($rank.$this->trump, $this->played, true)) {
                return $rank.$this->trump;
            }
        }

        return null;
    }

    /** Trumps not played yet and not in this hand. */
    public function trumpsOut(): int
    {
        if ($this->trump === null) {
            return 0;
        }

        return count(array_filter($this->unseen(), fn (string $c) => $c[1] === $this->trump))
            + count(array_filter(array_merge(...$this->known), fn (string $c) => $c[1] === $this->trump));
    }

    /**
     * What one play by $who, after $before in the same trick, tells about their hand.
     *
     * @param  list<array{seat: int, card: string}>  $before
     * @param  array<int, array<string, true>>  $voids
     * @param  array<int, int>  $cap
     */
    private function learn(array $before, int $who, string $card, array &$voids, array &$cap): void
    {
        $led = $before[0]['card'][1];
        if ($card[1] === $led) {
            return;
        }
        $voids[$who][$led] = true;
        if ($this->trump === null || $led === $this->trump) {
            return;
        }
        $cards = array_column($before, 'card');
        $winner = $before[BlotCards::winning($cards, $this->trump)]['seat'];
        if ($winner % 2 === $who % 2) {
            return;   // Partner winning: anything goes, nothing learned.
        }
        if ($card[1] !== $this->trump) {
            $voids[$who][$this->trump] = true;   // Had to trump, didn't: no trumps.

            return;
        }
        // Had to beat the highest trump on the table if they could.
        $highest = max(array_map(fn (string $c) => BlotCards::strength($c, $led, $this->trump), $cards));
        if ($highest >= 100 && BlotCards::strength($card, $led, $this->trump) < $highest) {
            $cap[$who] = min($cap[$who], $highest);
        }
    }

    /**
     * @return list<string>
     */
    public static function deck(): array
    {
        $cards = [];
        foreach (BlotCards::SUITS as $suit) {
            foreach (str_split('789TJQKA') as $rank) {
                $cards[] = $rank.$suit;
            }
        }

        return $cards;
    }
}
