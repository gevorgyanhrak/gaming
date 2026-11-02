<?php

namespace App\Gameplay\Reactions;

/**
 * What players can send each other at a table: emojis from a fixed set (by category, with search
 * words in every language the UI speaks) and predefined messages (keys; each viewer reads them in their
 * own language). Nothing else can be sent, so there is no free text to moderate. The single source for
 * the picker (GET /reactions) and for validation.
 */
final class ReactionCatalog
{
    /**
     * Emoji categories in picker order: emoji => search words (en, ru, hy).
     *
     * @var array<string, array<string, string>>
     */
    private const EMOJIS = [
        'smileys' => [
            '😀' => 'grin smile happy улыбка ժպիտ',
            '😄' => 'smile joy happy радость ուրախ',
            '😁' => 'beam grin улыбка ժպիտ',
            '😊' => 'blush happy nice довольный գոհ',
            '🙂' => 'slight smile улыбка ժպիտ',
            '😉' => 'wink подмигивание աչքով',
            '😌' => 'relieved calm спокойно հանգիստ',
            '😎' => 'cool sunglasses круто очки ակնոց',
            '🤔' => 'thinking hmm думаю մտածում',
            '😐' => 'neutral meh нейтрально անտարբեր',
            '😏' => 'smirk ухмылка քմծիծաղ',
            '😢' => 'cry sad грусть плач տխուր լաց',
            '😭' => 'sob crying рыдание լաց',
            '😡' => 'angry mad злой բարկացած',
            '😱' => 'scream shock шок սարսափ',
            '😴' => 'sleep tired сон քուն',
            '🥱' => 'yawn bored зевать հորանջ',
            '😇' => 'angel innocent ангел հրեշտակ',
            '🥺' => 'pleading please умоляю խնդրում',
        ],
        'reactions' => [
            '👍' => 'like thumbs up yes класс да լավ այո',
            '👎' => 'dislike thumbs down плохо վատ',
            '👏' => 'clap applause bravo аплодисменты браво ծափ',
            '🙌' => 'hooray raise hands ура ուռա',
            '👌' => 'ok perfect хорошо լավ',
            '🤝' => 'handshake deal рукопожатие ձեռքսեղմում',
            '🙏' => 'thanks please спасибо пожалуйста շնորհակալություն',
            '💪' => 'strong muscle сила ուժեղ',
            '🤞' => 'fingers crossed luck удача հաջողություն',
            '✌️' => 'peace victory мир հաղթանակ',
            '👋' => 'hello wave hi bye привет пока բարև',
            '🫡' => 'salute respect честь հարգանք',
            '🤷' => 'shrug dunno не знаю չգիտեմ',
            '🤦' => 'facepalm ugh фейспалм ափսոս',
            '👀' => 'eyes look watching смотрю աչքեր',
            '💯' => 'hundred perfect сто հարյուր',
        ],
        'funny' => [
            '😂' => 'laugh tears lol смех ծիծաղ',
            '🤣' => 'rofl laughing ржу ծիծաղ',
            '😆' => 'laughing haha хохот ծիծաղ',
            '😜' => 'wink tongue tease дразнить լեզու',
            '🤪' => 'crazy zany безумный խենթ',
            '😝' => 'tongue silly язык լեզու',
            '🙃' => 'upside down silly наоборот գլխիվայր',
            '🤡' => 'clown клоун ծաղրածու',
            '👻' => 'ghost boo призрак ուրվական',
            '🤓' => 'nerd smart ботаник խելոք',
            '🤯' => 'mind blown wow взрыв мозга ապշած',
            '😈' => 'devil evil чёртик սատանա',
            '🙈' => 'see no evil monkey обезьянка կապիկ',
            '🤖' => 'robot bot робот ռոբոտ',
            '👽' => 'alien пришелец այլմոլորակային',
            '🥸' => 'disguise incognito маскировка դիմակ',
        ],
        'love' => [
            '❤️' => 'heart love red сердце любовь սիրտ սեր',
            '😍' => 'heart eyes love влюблён սիրահարված',
            '🥰' => 'smiling hearts love любовь սեր',
            '😘' => 'kiss поцелуй համբույր',
            '💕' => 'two hearts сердца սրտեր',
            '💖' => 'sparkling heart сердце սիրտ',
            '💘' => 'cupid arrow купидон նետ',
            '💔' => 'broken heart разбитое сердце կոտրված սիրտ',
            '💙' => 'blue heart синее сердце կապույտ',
            '💚' => 'green heart зелёное сердце կանաչ',
            '💛' => 'yellow heart жёлтое сердце դեղին',
            '💜' => 'purple heart фиолетовое сердце մանուշակագույն',
            '🖤' => 'black heart чёрное сердце սև',
            '🤍' => 'white heart белое сердце սպիտակ',
            '🌹' => 'rose flower роза վարդ',
            '💐' => 'bouquet flowers букет цветы ծաղիկներ',
        ],
        'celebration' => [
            '🎉' => 'party tada celebrate праздник տոն',
            '🥳' => 'party face вечеринка խնջույք',
            '🎊' => 'confetti конфетти կոնֆետի',
            '🎈' => 'balloon шарик փուչիկ',
            '🎁' => 'gift present подарок նվեր',
            '🎂' => 'cake birthday торт день рождения տորթ',
            '🍾' => 'champagne bottle шампанское շամպայն',
            '🥂' => 'cheers toast тост կենաց',
            '✨' => 'sparkles shine блёстки փայլ',
            '🎆' => 'fireworks салют հրավառություն',
            '🎇' => 'sparkler бенгальский огонь հրավառություն',
            '🎶' => 'music notes музыка երաժշտություն',
            '💃' => 'dance dancer танец պար',
            '🕺' => 'dance man танцор պար',
            '🔥' => 'fire hot lit огонь կրակ',
            '🙌' => 'celebrate hooray ура ուռա',
        ],
        'gaming' => [
            '🎮' => 'game controller video game игра խաղ',
            '🕹️' => 'joystick arcade джойстик ջոյսթիք',
            '🎲' => 'dice roll кости зари զառ',
            '♟️' => 'chess pawn шахматы пешка շախմատ',
            '🎯' => 'target bullseye цель թիրախ',
            '🧩' => 'puzzle piece пазл գլուխկոտրուկ',
            '🏁' => 'finish flag финиш ավարտ',
            '⏳' => 'hourglass wait ждать սպասել',
            '⏰' => 'alarm time hurry время ժամանակ',
            '🧠' => 'brain smart мозг ուղեղ',
            '🍀' => 'luck clover удача բախտ',
            '🔮' => 'crystal ball predict предсказание գուշակություն',
            '⚡' => 'lightning fast молния կայծակ',
            '🚀' => 'rocket fast ракета հրթիռ',
            '🛡️' => 'shield defend щит վահան',
            '⚔️' => 'swords battle битва մարտ',
            '🪄' => 'magic wand магия կախարդանք',
        ],
        'poker' => [
            '🃏' => 'joker card джокер карта ջոկեր խաղաքարտ',
            '♠️' => 'spade suit пики масть խաղաքարտ',
            '♥️' => 'heart suit черви масть խաղաքարտ',
            '♦️' => 'diamond suit бубны масть խաղաքարտ',
            '♣️' => 'club suit трефы масть խաղաքարտ',
            '💰' => 'money bag pot деньги банк փող',
            '💵' => 'cash bill деньги փող',
            '🪙' => 'coin chip монета фишка մետաղադրամ ֆիշկա',
            '💎' => 'diamond gem бриллиант ադամանդ',
            '🎰' => 'slot jackpot джекпот ջեքփոթ',
            '🤑' => 'money face rich богач հարուստ',
            '😶' => 'poker face blank покерфейс դեմք',
            '🤫' => 'shh secret quiet тише секрет լուռ',
            '🫣' => 'peek peeking подглядывать նայել',
            '🙄' => 'eye roll bluff блеф закатить глаза բլեֆ',
            '🧐' => 'monocle examine изучаю զննում',
        ],
        'victory' => [
            '🏆' => 'trophy win winner кубок победа գավաթ հաղթանակ',
            '🥇' => 'gold medal first золото первый ոսկի առաջին',
            '🥈' => 'silver medal second серебро արծաթ',
            '🥉' => 'bronze medal third бронза բրոնզ',
            '🏅' => 'medal sports медаль մեդալ',
            '👑' => 'crown king queen корона король թագ թագավոր',
            '🎖️' => 'military medal honor орден շքանշան',
            '😤' => 'triumph huff торжество հաղթանակ',
            '😵' => 'dizzy knocked out нокаут պարտություն',
            '💀' => 'skull dead череп գանգ',
            '☠️' => 'skull crossbones dead череп գանգ',
            '🏳️' => 'white flag surrender сдаюсь հանձնվել',
            '😩' => 'weary tired устал հոգնած',
            '😞' => 'disappointed sad разочарован հիասթափված',
            '🫠' => 'melting таю հալվել',
            '😮‍💨' => 'exhale phew фух թեթևացում',
        ],
        'animals' => [
            '🐶' => 'dog puppy собака շուն',
            '🐱' => 'cat kitty кошка կատու',
            '🦁' => 'lion лев առյուծ',
            '🐯' => 'tiger тигр վագր',
            '🦊' => 'fox лиса աղվես',
            '🐻' => 'bear медведь արջ',
            '🐼' => 'panda панда պանդա',
            '🐨' => 'koala коала կոալա',
            '🐸' => 'frog лягушка գորտ',
            '🐵' => 'monkey обезьяна կապիկ',
            '🐔' => 'chicken курица հավ',
            '🐧' => 'penguin пингвин պինգվին',
            '🦉' => 'owl wise сова բու',
            '🦅' => 'eagle орёл արծիվ',
            '🐢' => 'turtle slow черепаха կրիա',
            '🦄' => 'unicorn единорог միաեղջյուր',
            '🐍' => 'snake змея օձ',
            '🦈' => 'shark акула շնաձուկ',
        ],
        'food' => [
            '🍕' => 'pizza пицца պիցցա',
            '🍔' => 'burger бургер բուրգեր',
            '🍟' => 'fries картошка фри կարտոֆիլ',
            '🌭' => 'hot dog хот-дог հոթդոգ',
            '🍿' => 'popcorn попкорн ադիբուդի',
            '🍩' => 'donut пончик պոնչիկ',
            '🍪' => 'cookie печенье թխվածքաբլիթ',
            '🍫' => 'chocolate шоколад շոկոլադ',
            '🍦' => 'ice cream мороженое պաղպաղակ',
            '🍎' => 'apple яблоко խնձոր',
            '🍉' => 'watermelon арбуз ձմերուկ',
            '🍇' => 'grapes виноград խաղող',
            '🍒' => 'cherries вишня բալ',
            '☕' => 'coffee кофе սուրճ',
            '🍵' => 'tea чай թեյ',
            '🍺' => 'beer пиво գարեջուր',
            '🍷' => 'wine вино գինի',
            '🥤' => 'drink soda напиток ըմպելիք',
        ],
        'sports' => [
            '⚽' => 'soccer football футбол ֆուտբոլ',
            '🏀' => 'basketball баскетбол բասկետբոլ',
            '🏈' => 'american football регби ռեգբի',
            '⚾' => 'baseball бейсбол բեյսբոլ',
            '🎾' => 'tennis теннис թենիս',
            '🏐' => 'volleyball волейбол վոլեյբոլ',
            '🏓' => 'ping pong table tennis настольный теннис սեղանի թենիս',
            '🥊' => 'boxing glove бокс բռնցքամարտ',
            '🥋' => 'martial arts karate каратэ կարատե',
            '⛳' => 'golf гольф գոլֆ',
            '🏒' => 'hockey хоккей հոկեյ',
            '🎳' => 'bowling боулинг բոուլինգ',
            '🏋️' => 'weightlifting gym штанга ծանրամարտ',
            '🚴' => 'cycling bike велосипед հեծանիվ',
            '🏊' => 'swimming плавание լող',
            '🤸' => 'gymnastics cartwheel гимнастика մարմնամարզություն',
        ],
        'popular' => [
            '⭐' => 'star звезда աստղ',
            '🌟' => 'glowing star shine сияние աստղ',
            '🌈' => 'rainbow радуга ծիածան',
            '☀️' => 'sun sunny солнце արև',
            '🌙' => 'moon night луна ночь լուսին',
            '❄️' => 'snow cold снег ձյուն',
            '💤' => 'zzz sleep сон քուն',
            '💥' => 'boom explosion бум պայթյուն',
            '💫' => 'dizzy stars звёзды աստղեր',
            '💬' => 'speech chat сообщение հաղորդագրություն',
            '❓' => 'question вопрос հարց',
            '❗' => 'exclamation attention внимание ուշադրություն',
            '✅' => 'check yes done да готово այո',
            '❌' => 'cross no нет ոչ',
            '🔔' => 'bell ring колокол զանգ',
            '🇦🇲' => 'armenia flag армения флаг հայաստան դրոշ',
        ],
    ];

    /**
     * Predefined messages by category (keys; the texts are the client's translations).
     *
     * @var array<string, list<string>>
     */
    private const MESSAGES = [
        'greetings' => ['hello', 'good_luck', 'have_fun', 'ready', 'wait'],
        'praise' => ['nice_move', 'well_played', 'youre_strong', 'good_game', 'gg', 'lucky'],
        'fun' => ['haha', 'oops', 'wow', 'almost_got_you', 'think'],
        'after' => ['thank_you', 'play_again', 'rematch', 'better_luck', 'bye'],
    ];

    /**
     * @return list<array{slug: string, emojis: list<array{emoji: string, keywords: string}>}>
     */
    public function emojiCategories(): array
    {
        return array_map(
            fn (string $slug) => [
                'slug' => $slug,
                'emojis' => array_map(
                    fn (string $emoji) => ['emoji' => $emoji, 'keywords' => self::EMOJIS[$slug][$emoji]],
                    array_map('strval', array_keys(self::EMOJIS[$slug])),
                ),
            ],
            array_keys(self::EMOJIS),
        );
    }

    /**
     * @return list<array{slug: string, keys: list<string>}>
     */
    public function messageCategories(): array
    {
        return array_map(fn (string $slug) => ['slug' => $slug, 'keys' => self::MESSAGES[$slug]], array_keys(self::MESSAGES));
    }

    public function has(string $kind, string $value): bool
    {
        return match ($kind) {
            'emoji' => collect(self::EMOJIS)->contains(fn (array $emojis) => array_key_exists($value, $emojis)),
            'message' => collect(self::MESSAGES)->flatten()->contains($value),
            default => false,
        };
    }
}
