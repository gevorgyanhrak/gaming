<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Blot's rules as the game plays them now (docs/games/blot-rules.md): bids to 80, Quansh at any moment of the
 * bazar, the bidder's own OK before play (owner, 2026-11-14), never forced to undertrump, scoring in sai — in
 * English, Russian and Armenian. Only a text still as first seeded is replaced: an administrator's edits stay.
 */
return new class extends Migration
{
    private const OLD = 'Four players in two partnerships (partners sit opposite) play with 32 cards, seven to ace, eight each. The first team to 301 points wins.

The bazar: starting left of the dealer, each player bids a number (8 to 30, worth ten points each) and a trump suit higher than the last bid, or passes. An opponent of the bidder may double, and the bidding team may then redouble. Three passes end the bidding; if all four pass, the cards are dealt again.

Play: the player left of the dealer leads. You must follow suit; when trumps are led you must beat the highest trump if you can. Without the led suit you must trump, beating any trump already played if you can, unless your partner is winning the trick. The highest trump, or else the highest card of the led suit, takes the trick.

Trumps rank jack (20), nine (14), ace (11), ten (10), king (4), queen (3), eight, seven; other suits ace (11), ten (10), king (4), queen (3), jack (2), nine, eight, seven. The last trick adds 10: 162 in all, or 250 for taking every trick.

Declarations are announced in the first trick: three cards in sequence 20, four 50, five 100; four jacks 200, four nines 150, four aces, tens, kings or queens 100. Only the team with the best declaration scores its declarations. The king and queen of trumps in one hand (belote) score 20.

The bidding team must reach its bid with tricks, declarations and belote. If it does, each team scores its points; if not, the opponents score 162 and every declaration. In a doubled hand the winning team takes everything, doubled (redoubled: four times).';

    public function up(): void
    {
        $game = DB::table('games')->where('slug', 'blot')->first(['id', 'rules']);
        $rules = $game === null ? null : json_decode((string) $game->rules, true);
        if (! is_array($rules) || ($rules['en'] ?? null) !== self::OLD) {
            return;
        }
        DB::table('games')->where('id', $game->id)->update(['rules' => json_encode([
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
        ], JSON_UNESCAPED_UNICODE)]);
    }

    public function down(): void {}
};
