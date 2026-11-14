<?php

namespace Database\Seeders;

use App\Gameplay\GameRegistry;
use App\Models\Game;
use App\Models\GameCategory;
use Illuminate\Database\Seeder;

/**
 * Seeds the games: the five launch games and those added since (Blackjack, Blot, Domino, Ludo, 8-ball pool, Rummy).
 *
 * Idempotent and non-destructive: existing games are left untouched, so re-running
 * the seeder never overwrites changes made by administrators. A game is created playable only
 * if its engine is registered. Descriptions and rules
 * are seeded in English; other locales fall back to English until translated in the admin panel.
 */
class GameSeeder extends Seeder
{
    /** Games on the "Card games" shelf; the rest are board games. */
    private const CARD_GAMES = ['durak', 'poker', 'blackjack', 'blot', 'rummy'];

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
                'rules' => [
                    'en' => implode("\n\n", [
                        'Four players in two partnerships (partners sit opposite) play with 32 cards, seven to ace, eight each. A match is played to 101, 151 or 301 (the mode); the first pair to reach it wins.',
                        'The bazar: starting left of the dealer, each player bids a number from 8 to 80 (ten points each) with a trump suit or no-trump (Boy), higher than the last bid, or passes. Kaput — all eight tricks — outbids any number. An opponent of the bidder may say Quansh (double) at any moment of the bazar, and the bidding pair may answer with Sur (redouble). When the other three pass, the bazar comes back to the bidder, who may raise the bid or say OK; play starts only then. If all four pass without a bid, the cards are dealt again.',
                        'Play: the player left of the dealer leads. Follow suit with any card of it (a trump lead with any trump). Without the led suit: if your partner is winning the trick, play any card; if an opponent is winning with a card of another suit, you must trump; if an opponent has trumped, you must play a higher trump if you have one, otherwise any card. The highest trump, or else the highest card of the led suit, takes the trick.',
                        'Trumps rank jack (20), nine (14), ace (11), ten (10), king (4), queen (3), eight, seven; other suits ace (11), ten (10), king (4), queen (3), jack (2), nine, eight, seven. In no-trump an ace is worth 19. The last trick adds 10.',
                        'Declarations are announced before your first card: three cards in sequence 20, four 50, five 100; four jacks 200, four nines 140, four aces 110, four tens, kings or queens 100. Only the pair with the best declaration scores its declarations. The king and queen of trumps in one hand (Blot, then Reblot) always score 20.',
                        'Scores are in sai (points ÷ 10). The bidding pair must reach its bid with card points, declarations and Blot. Made: it scores the bid plus 16 less the opponents\' card sai, and the opponents keep their card sai; failed: the opponents score the bid, 16 and every declaration. Taking all eight tricks is worth 25 instead of 16. Quansh doubles the bid and Sur quadruples it (no-trump: ×2, ×3, ×5).',
                    ]),
                    'ru' => implode("\n\n", [
                        'Четыре игрока в двух парах (партнёры сидят напротив) играют колодой из 32 карт, от семёрки до туза, по восемь каждому. Партия идёт до 101, 151 или 301 (режим); побеждает пара, первой набравшая эту сумму.',
                        'Базар: начиная слева от сдающего, каждый называет число от 8 до 80 (по десять очков) с козырем или без козыря (Бой) — выше последней заявки — или пасует. Капут — все восемь взяток — перебивает любое число. Соперник заявившего может в любой момент базара сказать Куанш (контра), а заявившая пара — ответить Сур (реконтра). Когда трое остальных спасовали, базар возвращается к заявившему: он может повысить заявку или сказать «Пас», и только тогда начинается игра. Если все четверо спасовали без заявки, карты сдаются заново.',
                        'Игра: первым ходит игрок слева от сдающего. В масть ходят любой её картой (на козырь — любым козырем). Без масти: если взятку берёт партнёр — любая карта; если соперник берёт картой другой масти — нужно бить козырем; если соперник уже побил козырем — нужно положить старший козырь, если он есть, иначе любую карту. Взятку берёт старший козырь, иначе старшая карта масти хода.',
                        'Козыри по старшинству: валет (20), девятка (14), туз (11), десятка (10), король (4), дама (3), восьмёрка, семёрка; другие масти: туз (11), десятка (10), король (4), дама (3), валет (2), девятка, восьмёрка, семёрка. Без козыря туз стоит 19. Последняя взятка добавляет 10.',
                        'Комбинации объявляют до своей первой карты: три карты подряд — 20, четыре — 50, пять — 100; четыре валета — 200, четыре девятки — 140, четыре туза — 110, четыре десятки, короля или дамы — 100. Очки за комбинации получает только пара с лучшей комбинацией. Король и дама козыря в одной руке (Блот, затем Реблот) всегда дают 20.',
                        'Счёт ведётся в саях (очки ÷ 10). Заявившая пара должна набрать свою заявку очками за карты, комбинации и Блот. Выполнено: она получает заявку плюс 16 минус саи соперников за карты, а соперники — свои саи за карты; не выполнено: соперники получают заявку, 16 и все комбинации. Все восемь взяток стоят 25 вместо 16. Куанш удваивает заявку, Сур — учетверяет (без козыря: ×2, ×3, ×5).',
                    ]),
                    'hy' => implode("\n\n", [
                        'Չորս խաղացող երկու զույգով (զուգընկերները նստում են դեմ դիմաց) խաղում են 32 թղթով՝ յոթից մինչև տուզ, յուրաքանչյուրին ութ։ Խաղը գնում է մինչև 101, 151 կամ 301 (ռեժիմը). հաղթում է այն զույգը, որն առաջինը կհավաքի։',
                        'Բազար. սկսած բաժանողի ձախից՝ յուրաքանչյուրը հայտարարում է 8-ից 80 թիվ (յուրաքանչյուրը տասը միավոր) կոզրով կամ առանց կոզրի (Բոյ)՝ վերջին հայտից բարձր, կամ ասում է «Լավ ա»։ Կապուտը՝ բոլոր ութ ձեռքը, գերազանցում է ցանկացած թիվ։ Հայտարարողի մրցակիցը բազարի ցանկացած պահի կարող է ասել Քուանշ (կրկնապատկում), իսկ հայտարարող զույգը կարող է պատասխանել Սուր։ Երբ մյուս երեքն ասել են «Լավ ա», բազարը վերադառնում է հայտարարողին. նա կարող է բարձրացնել հայտը կամ ասել «Լավ ա», և միայն այդ ժամանակ սկսվում է խաղը։ Եթե չորսն էլ առանց հայտի ասել են «Լավ ա», թղթերը նորից են բաժանվում։',
                        'Խաղ. առաջինը քայլում է բաժանողի ձախից նստածը։ Գույնին պատասխանում են նույն գույնի ցանկացած թղթով (կոզրին՝ ցանկացած կոզրով)։ Առանց այդ գույնի. եթե ձեռքը վերցնում է զուգընկերը՝ ցանկացած թուղթ. եթե մրցակիցը վերցնում է այլ գույնի թղթով՝ պետք է կոզր գցել. եթե մրցակիցն արդեն կոզր է գցել՝ պետք է ավելի բարձր կոզր գցել, եթե կա, հակառակ դեպքում՝ ցանկացած թուղթ։ Ձեռքը վերցնում է ամենաբարձր կոզրը, իսկ առանց դրա՝ քայլած գույնի ամենաբարձր թուղթը։',
                        'Կոզրերն ըստ ավագության. վալետ (20), ինը (14), տուզ (11), տասը (10), թագավոր (4), դամա (3), ութ, յոթ. մյուս գույները՝ տուզ (11), տասը (10), թագավոր (4), դամա (3), վալետ (2), ինը, ութ, յոթ։ Բոյում տուզն արժե 19։ Վերջին ձեռքն ավելացնում է 10։',
                        'Կոմբինացիաները հայտարարվում են առաջին թուղթը գցելուց առաջ. երեք հաջորդական թուղթ՝ 20, չորսը՝ 50, հինգը՝ 100. չորս վալետ՝ 200, չորս ինը՝ 140, չորս տուզ՝ 110, չորս տասը, թագավոր կամ դամա՝ 100։ Կոմբինացիաների միավորները ստանում է միայն լավագույն կոմբինացիայով զույգը։ Կոզրի թագավորն ու դաման մեկ ձեռքում (Բլոտ, ապա Ռեբլոտ) միշտ տալիս են 20։',
                        'Հաշիվը սայերով է (միավորներ ÷ 10)։ Հայտարարող զույգը պետք է հավաքի իր հայտը թղթերի միավորներով, կոմբինացիաներով և Բլոտով։ Կատարված է. ստանում է հայտը գումարած 16՝ հանած մրցակիցների թղթերի սայերը, իսկ մրցակիցները՝ իրենց թղթերի սայերը. չկատարված է. մրցակիցները ստանում են հայտը, 16-ը և բոլոր կոմբինացիաները։ Բոլոր ութ ձեռքն արժե 25՝ 16-ի փոխարեն։ Քուանշը կրկնապատկում է հայտը, Սուրը՝ քառապատկում (Բոյում՝ ×2, ×3, ×5)։',
                    ]),
                ],
                'min_players' => 4,
                'max_players' => 4,
            ],
            [
                'slug' => 'domino',
                'name' => ['en' => 'Domino', 'ru' => 'Домино', 'hy' => 'Դոմինո'],
                'description' => ['en' => 'Block dominoes for 2 to 4 players. Match the ends, empty your hand, reach 100.', 'ru' => 'Блок-домино на 2–4 игроков. Подбирайте концы, избавьтесь от костей, наберите 100.', 'hy' => 'Բլոկ դոմինո 2-4 խաղացողի համար։ Համապատասխանեցրեք ծայրերը, դատարկեք ձեռքը, հավաքեք 100։'],
                'rules' => ['en' => implode("\n\n", [
                    'Block dominoes is played with a double-six set of 28 tiles by 2 to 4 players. Each player takes seven tiles; any tiles left over stay face down and are not used.',
                    'In the first round the player with the highest double plays it first (if nobody has a double, the heaviest tile). In later rounds the winner of the last round starts with any tile.',
                    'In turn, add a tile to either end of the line so that its touching half matches the number at that end. If you can\'t play, you pass; there is no drawing.',
                    'The round ends when a player plays their last tile: they score the pips left in all the other hands. If nobody can play, the round is blocked and the player with the fewest pips scores everyone else\'s pips; a tie scores nothing.',
                    'The first player to reach 100 points wins.',
                ])],
                'min_players' => 2,
                'max_players' => 4,
            ],
            [
                'slug' => 'ludo',
                'name' => ['en' => 'Ludo', 'ru' => 'Лудо', 'hy' => 'Լուդո'],
                'description' => ['en' => 'Race your four tokens round the board and home. Roll a six to start, and send rivals back to their yard.', 'ru' => 'Проведите четыре фишки по кругу домой. Шестёрка выводит фишку, а соперников можно отправить обратно.', 'hy' => 'Անցկացրեք չորս խաղաքարերը շրջանով և տուն։ Վեցը հանում է խաղաքարը, իսկ մրցակիցներին կարելի է հետ ուղարկել։'],
                'rules' => ['en' => implode("\n\n", [
                    'Two to four players each have four tokens in their yard. With two players, they take opposite colours.',
                    'On your turn roll the die and move one token that many squares. A token leaves the yard only on a six, onto your start square. A six gives you another roll, but a third six in a row ends your turn.',
                    'Tokens travel once round the track, then up your home column to the centre, which takes the exact roll. If no token can move, the turn passes.',
                    'Landing on an opponent\'s token sends it back to its yard, except on the safe squares: the four start squares and the four stars. Your own tokens can share a square.',
                    'The first player to bring all four tokens home wins.',
                ])],
                'min_players' => 2,
                'max_players' => 4,
            ],
            [
                'slug' => 'pool',
                'name' => ['en' => '8-ball pool', 'ru' => 'Пул «восьмёрка»', 'hy' => '8-գնդակ փուլ'],
                'description' => ['en' => 'Classic 8-ball, one on one. Aim, set your power and clear your group before the black.', 'ru' => 'Классическая «восьмёрка» один на один. Цельтесь, выбирайте силу удара и забейте свою группу до чёрного.', 'hy' => 'Դասական 8-գնդակ, մեկը մեկի դեմ։ Նշան բռնեք, ընտրեք հարվածի ուժը և գցեք ձեր խումբը սևից առաջ։'],
                'rules' => ['en' => implode("\n\n", [
                    'Two players, fifteen numbered balls and the white cue ball. Balls 1–7 are solids, 9–15 stripes; the 8 is black. The first player breaks from the head spot.',
                    'The table is open after the break: the first ball you pot without a foul makes that group yours, and the other group your opponent\'s. After potting one of your balls without a foul you shoot again.',
                    'Fouls: potting the cue ball, hitting no ball, hitting first a ball that is not yours (on an open table, the 8), or no ball reaching a cushion after the hit when nothing is potted. After a foul your opponent places the cue ball anywhere on the table.',
                    'Once your group is cleared, pot the 8 to win. Potting the 8 before that, or with a foul, loses the game. An 8 potted on the break goes back on the foot spot.',
                    'Shots are played out on the server: you choose the direction and the power, and every player sees the same result.',
                ])],
                'min_players' => 2,
                'max_players' => 2,
            ],
            [
                'slug' => 'rummy',
                'name' => ['en' => 'Rummy', 'ru' => 'Рамми', 'hy' => 'Ռամի'],
                'description' => ['en' => 'Classic rummy for 2 to 6: draw, make sets and runs, go out first and reach 100.', 'ru' => 'Классический рамми на 2–6 игроков: берите карты, собирайте сеты и стриты, выходите первым и наберите 100.', 'hy' => 'Դասական ռամի 2-6 խաղացողի համար. վերցրեք թղթեր, հավաքեք սեթեր և շարքեր, դուրս եկեք առաջինը և հավաքեք 100։'],
                'rules' => ['en' => implode("\n\n", [
                    'Two to six players use one 52-card deck (two decks for five or six). With two players each gets ten cards, with three or four seven, with five or six six. The next card starts the discard pile.',
                    'On your turn, draw the top card of the stock or of the discard pile. Then you may put down melds — a set of three or four cards of the same rank in different suits, or a run of three or more cards in sequence in one suit (aces are low) — and lay off cards that extend melds already on the table. End your turn by discarding a card; you may not discard the card you just took from the discard pile.',
                    'The first player to get rid of all their cards wins the round and scores the cards left in the other hands: aces 1, pictures 10, other cards their number.',
                    'When the stock runs out, the discard pile is shuffled into a new stock once; if that runs out too, the round ends and nobody scores. The first player to reach 100 points wins.',
                ])],
                'min_players' => 2,
                'max_players' => 6,
            ],
        ];
    }
}
