<?php

namespace Database\Seeders;

use App\Models\Game;
use Illuminate\Database\Seeder;

/**
 * Seeds the five launch games.
 *
 * Idempotent and non-destructive: existing games are left untouched, so re-running
 * the seeder never overwrites changes made by administrators. Games ship hidden from
 * play (is_enabled = false) until their engine is implemented. Descriptions and rules
 * are seeded in English; other locales fall back to English until translated in the admin panel.
 */
class GameSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->games() as $index => $game) {
            Game::query()->firstOrCreate(
                ['slug' => $game['slug']],
                [...$game, 'sort_order' => ($index + 1) * 10, 'is_enabled' => false, 'is_visible' => true],
            );
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function games(): array
    {
        return [
            [
                'slug' => 'nardi',
                'name' => ['en' => 'Nardi', 'ru' => 'Нарды', 'hy' => 'Նարդի'],
                'description' => ['en' => 'The classic race of strategy and luck. Move all fifteen checkers around the board and bear them off before your opponent.'],
                'rules' => ['en' => implode("\n\n", [
                    'Each player has fifteen checkers and moves them around the 24 points in opposite directions, according to the roll of two dice.',
                    'Each die is a separate move, and doubles are played four times. You must use both dice whenever possible; if only one can be played, you must play the higher one when you can.',
                    'A point held by two or more of your opponent\'s checkers is blocked. Landing on a single opposing checker hits it and sends it to the bar, from where it must re-enter in your home board before its owner can make any other move.',
                    'Once all of your checkers are in your home board, you may bear them off. The first player to bear off all fifteen checkers wins. Winning before your opponent has borne off a single checker is a mars and counts double.',
                ])],
                'min_players' => 2,
                'max_players' => 2,
            ],
            [
                'slug' => 'chess',
                'name' => ['en' => 'Chess', 'ru' => 'Шахматы', 'hy' => 'Շախմատ'],
                'description' => ['en' => 'The timeless game of strategy. Outmaneuver your opponent and checkmate their king.'],
                'rules' => ['en' => implode("\n\n", [
                    'White moves first and players alternate moves. The king moves one square in any direction, the queen any distance in a straight line or diagonal, the rook along ranks and files, the bishop along diagonals, and the knight in an L-shape, jumping over other pieces. Pawns move forward one square, or two from their starting square, and capture diagonally.',
                    'Special moves are castling, en passant capture, and promotion of a pawn that reaches the last rank.',
                    'You may never make a move that leaves your own king in check. Checkmate, when the king is in check and has no legal escape, wins the game.',
                    'The game is drawn by stalemate, threefold repetition, the fifty-move rule, insufficient material or agreement. Running out of time loses, unless your opponent has no way to checkmate.',
                ])],
                'min_players' => 2,
                'max_players' => 2,
            ],
            [
                'slug' => 'checkers',
                'name' => ['en' => 'Checkers', 'ru' => 'Шашки', 'hy' => 'Շաշկի'],
                'description' => ['en' => 'Russian draughts on an 8×8 board. Jump, capture and crown your pieces to clear the board.'],
                'rules' => ['en' => implode("\n\n", [
                    'The game is played on the dark squares of an 8×8 board, and each player starts with twelve pieces. White moves first. Men move one square diagonally forward.',
                    'Capturing is mandatory: jump diagonally over an adjacent opposing piece onto the empty square behind it. Men capture both forwards and backwards, and a capturing sequence must be completed to the end. When several captures are available, you may choose any of them.',
                    'A man that reaches the far row becomes a king. Kings move any distance diagonally and capture from a distance. A man that reaches the far row during a capture is crowned immediately and continues capturing as a king.',
                    'You win by capturing all of your opponent\'s pieces or leaving them without a legal move.',
                ])],
                'min_players' => 2,
                'max_players' => 2,
            ],
            [
                'slug' => 'durak',
                'name' => ['en' => 'Durak', 'ru' => 'Дурак', 'hy' => 'Դուրակ'],
                'description' => ['en' => 'The famous card game of attack and defense. Get rid of all your cards — the last player holding cards is the fool.'],
                'rules' => ['en' => implode("\n\n", [
                    'Durak is played with a 36-card deck, from six to ace. Each player is dealt six cards, and the next card is turned face up to set the trump suit. The player with the lowest trump attacks first.',
                    'The attacker plays a card. The defender must beat it with a higher card of the same suit or with any trump; a trump can only be beaten by a higher trump. Attackers may throw in more cards of any rank already on the table, up to six attacks per bout and never more than the defender holds.',
                    'If the defender beats every card, the bout ends and the defender attacks next. Otherwise the defender picks up all the cards on the table and loses the turn to attack.',
                    'After each bout, players draw back up to six cards while the deck lasts. Once the deck is empty, players who run out of cards leave the game. The last player left holding cards is the durak and loses.',
                ])],
                'min_players' => 2,
                'max_players' => 6,
            ],
            [
                'slug' => 'poker',
                'name' => ['en' => 'Poker', 'ru' => 'Покер', 'hy' => 'Պոկեր'],
                'description' => ['en' => 'No-Limit Texas Hold\'em with play chips. Read your opponents, size your bets and take the pot.'],
                'rules' => ['en' => implode("\n\n", [
                    'Each player receives two private hole cards. Five community cards are dealt face up in three stages: the flop (three cards), the turn and the river.',
                    'There is a betting round before the flop and after each stage. On your turn you may fold, check, call, bet or raise; in No-Limit, a bet can be any amount up to all of your chips. The two players to the left of the dealer post the small and big blinds.',
                    'At showdown, the best five-card hand made from your hole cards and the community cards wins the pot, unless everyone else has folded. From highest to lowest: straight flush, four of a kind, full house, flush, straight, three of a kind, two pair, one pair, high card.',
                    'Chips are play chips with no monetary value. They cannot be bought, sold or transferred.',
                ])],
                'min_players' => 2,
                'max_players' => 6,
            ],
        ];
    }
}
