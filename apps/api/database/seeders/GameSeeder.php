<?php

namespace Database\Seeders;

use App\Gameplay\GameRegistry;
use App\Models\Game;
use App\Models\GameCategory;
use Illuminate\Database\Seeder;

/**
 * Seeds the games: the five launch games and those added since (Blackjack, Blot).
 *
 * Idempotent and non-destructive: existing games are left untouched, so re-running
 * the seeder never overwrites changes made by administrators. A game is created playable only
 * if its engine is registered. Descriptions and rules
 * are seeded in English; other locales fall back to English until translated in the admin panel.
 */
class GameSeeder extends Seeder
{
    /** Games on the "Card games" shelf; the rest are board games. */
    private const CARD_GAMES = ['durak', 'poker', 'blackjack', 'blot'];

    public function run(): void
    {
        foreach ($this->games() as $index => $game) {
            // New games start playable only if their engine exists; afterwards admins decide.
            Game::query()->firstOrCreate(
                ['slug' => $game['slug']],
                [...$game, 'game_category_id' => GameCategory::query()->where('slug', in_array($game['slug'], self::CARD_GAMES, true) ? 'card' : 'board')->value('id'), 'sort_order' => ($index + 1) * 10, 'is_enabled' => app(GameRegistry::class)->has($game['slug']), 'is_visible' => true],
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
                'description' => ['en' => 'The classic race of strategy and luck. Move all fifteen checkers around the board and bear them off before your opponent.', 'ru' => 'Классическая гонка стратегии и удачи. Проведите все пятнадцать шашек по доске и выведите их раньше соперника.', 'hy' => 'Ռազմավարության ու հաջողության դասական մրցավազք։ Անցկացրեք բոլոր տասնհինգ քարերը տախտակով և հանեք դրանք մրցակցից առաջ։'],
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
                'description' => ['en' => 'The timeless game of strategy. Outmaneuver your opponent and checkmate their king.', 'ru' => 'Вечная игра стратегии. Перехитрите соперника и поставьте мат его королю.', 'hy' => 'Ռազմավարության հավերժական խաղ։ Գերազանցեք մրցակցին և մատ հայտարարեք նրա արքային։'],
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
                'description' => ['en' => 'Russian draughts on an 8×8 board. Jump, capture and crown your pieces to clear the board.', 'ru' => 'Русские шашки на доске 8×8. Бейте шашки соперника, проходите в дамки и очистите доску.', 'hy' => 'Ռուսական շաշկի 8×8 տախտակի վրա։ Հարվածեք մրցակցի քարերին, դուրս եկեք դամկա և մաքրեք տախտակը։'],
                'rules' => ['en' => implode("\n\n", [
                    'The game is played on the dark squares of an 8×8 board, and each player starts with twelve pieces. White moves first. Men move one square diagonally forward.',
                    'Capturing is mandatory: jump diagonally over an adjacent opposing piece onto the empty square behind it. Men capture both forwards and backwards, and a capturing sequence must be completed to the end. When several captures are available, you may choose any of them.',
                    'A man that reaches the far row becomes a king. Kings move any distance diagonally and capture from a distance. A man that reaches the far row during a capture is crowned immediately and continues capturing as a king.',
                    'You win by capturing all of your opponent\'s pieces or leaving them without a legal move. The game is drawn when the same position occurs for the third time with the same player to move, or after fifteen moves by each player made only with kings and without a capture.',
                ])],
                'min_players' => 2,
                'max_players' => 2,
            ],
            [
                'slug' => 'durak',
                'name' => ['en' => 'Durak', 'ru' => 'Дурак', 'hy' => 'Դուրակ'],
                'description' => ['en' => 'The famous card game of attack and defense. Get rid of all your cards — the last player holding cards is the fool.', 'ru' => 'Знаменитая карточная игра атаки и защиты. Избавьтесь от всех карт: последний с картами на руках — дурак.', 'hy' => 'Հարձակման և պաշտպանության հայտնի թղթախաղ։ Ազատվեք բոլոր թղթերից. վերջինը, ում ձեռքին թղթեր մնան, դուրակն է։'],
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
                'description' => ['en' => 'No-Limit Texas Hold\'em with play chips. Read your opponents and make the best hand.', 'ru' => 'Безлимитный техасский холдем на игровые фишки. Читайте соперников и собирайте лучшую комбинацию.', 'hy' => 'Առանց սահմանափակման Տեխասյան հոլդեմ՝ խաղային ֆիշկաներով։ Կարդացեք մրցակիցներին և հավաքեք լավագույն համակցությունը։'],
                'rules' => ['en' => implode("\n\n", [
                    'Each player receives two private hole cards. Five community cards are dealt face up in three stages: the flop (three cards), the turn and the river.',
                    'There is a betting round before the flop and after each stage. On your turn you may fold, check, call, bet or raise; in No-Limit, a bet can be any amount up to all of your chips. The two players to the left of the dealer post the small and big blinds.',
                    'At showdown, the best five-card hand made from your hole cards and the community cards wins the pot, unless everyone else has folded. From highest to lowest: straight flush, four of a kind, full house, flush, straight, three of a kind, two pair, one pair, high card.',
                    'Chips are play chips with no monetary value. They cannot be bought, sold or transferred.',
                ])],
                'min_players' => 2,
                'max_players' => 6,
            ],
            [
                'slug' => 'blackjack',
                'name' => ['en' => 'Blackjack', 'ru' => 'Блэкджек', 'hy' => 'Բլեքջեք'],
                'description' => ['en' => 'Beat the dealer to 21. Play alone or at a table of up to five, with play chips.', 'ru' => 'Обыграйте дилера, набрав 21. Играйте один или за столом до пяти игроков, на игровые фишки.', 'hy' => 'Հաղթեք դիլերին՝ հավաքելով 21։ Խաղացեք մենակ կամ մինչև հինգ հոգանոց սեղանի շուրջ՝ խաղային ֆիշկաներով։'],
                'rules' => ['en' => implode("\n\n", [
                    'Each player plays against the dealer, not against each other. Cards two to ten count their value, picture cards count ten, and an ace counts eleven or one. Get closer to 21 than the dealer without going over.',
                    'Place your bet, then you and the dealer receive two cards; one of the dealer\'s is face down. An ace and a ten-value card is a blackjack and pays 3 to 2, unless the dealer has one too. With an ace or a ten showing, the dealer checks for blackjack first.',
                    'On your turn, hit to take a card or stand to keep your total. Double down on your first two cards to double your bet and take exactly one more card. Split two cards of the same value into two hands with a second bet; split aces receive one card each.',
                    'Going over 21 loses at once. The dealer then draws until reaching 17 or more and stands on soft 17. A win pays 1 to 1 and a tie returns your bet. The game lasts a set number of rounds; finishing with more chips than you brought is a win.',
                    'Chips are play chips with no monetary value. They cannot be bought, sold or transferred.',
                ])],
                'min_players' => 1,
                'max_players' => 5,
            ],
            [
                'slug' => 'blot',
                'name' => ['en' => 'Blot', 'ru' => 'Блот', 'hy' => 'Բլոտ'],
                'description' => ['en' => 'Bazar blot for four: two partnerships bid for trumps and race to 301.', 'ru' => 'Базар-блот на четверых: две пары торгуются за козырь и играют до 301.', 'hy' => 'Բազար բլոտ չորսով. երկու զույգ սակարկում են հաղթաթղթի համար և խաղում մինչև 301։'],
                'rules' => ['en' => implode("\n\n", [
                    'Four players in two partnerships (partners sit opposite) play with 32 cards, seven to ace, eight each. The first team to 301 points wins.',
                    'The bazar: starting left of the dealer, each player bids a number (8 to 30, worth ten points each) and a trump suit higher than the last bid, or passes. An opponent of the bidder may double, and the bidding team may then redouble. Three passes end the bidding; if all four pass, the cards are dealt again.',
                    'Play: the player left of the dealer leads. You must follow suit; when trumps are led you must beat the highest trump if you can. Without the led suit you must trump, beating any trump already played if you can, unless your partner is winning the trick. The highest trump, or else the highest card of the led suit, takes the trick.',
                    'Trumps rank jack (20), nine (14), ace (11), ten (10), king (4), queen (3), eight, seven; other suits ace (11), ten (10), king (4), queen (3), jack (2), nine, eight, seven. The last trick adds 10: 162 in all, or 250 for taking every trick.',
                    'Declarations are announced in the first trick: three cards in sequence 20, four 50, five 100; four jacks 200, four nines 150, four aces, tens, kings or queens 100. Only the team with the best declaration scores its declarations. The king and queen of trumps in one hand (belote) score 20.',
                    'The bidding team must reach its bid with tricks, declarations and belote. If it does, each team scores its points; if not, the opponents score 162 and every declaration. In a doubled hand the winning team takes everything, doubled (redoubled: four times).',
                ])],
                'min_players' => 4,
                'max_players' => 4,
            ],
        ];
    }
}
