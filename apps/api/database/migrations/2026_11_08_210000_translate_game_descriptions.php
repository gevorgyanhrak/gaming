<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Russian and Armenian descriptions for the launch games, now shown on the home page's cards (TASK-056), and
 * poker's English line without wagering words. Text an administrator already wrote is left alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        $translations = [
            'nardi' => ['ru' => 'Классическая гонка стратегии и удачи. Проведите все пятнадцать шашек по доске и выведите их раньше соперника.', 'hy' => 'Ռազմավարության ու հաջողության դասական մրցավազք։ Անցկացրեք բոլոր տասնհինգ քարերը տախտակով և հանեք դրանք մրցակցից առաջ։'],
            'chess' => ['ru' => 'Вечная игра стратегии. Перехитрите соперника и поставьте мат его королю.', 'hy' => 'Ռազմավարության հավերժական խաղ։ Գերազանցեք մրցակցին և մատ հայտարարեք նրա արքային։'],
            'checkers' => ['ru' => 'Русские шашки на доске 8×8. Бейте шашки соперника, проходите в дамки и очистите доску.', 'hy' => 'Ռուսական շաշկի 8×8 տախտակի վրա։ Հարվածեք մրցակցի քարերին, դուրս եկեք դամկա և մաքրեք տախտակը։'],
            'durak' => ['ru' => 'Знаменитая карточная игра атаки и защиты. Избавьтесь от всех карт: последний с картами на руках — дурак.', 'hy' => 'Հարձակման և պաշտպանության հայտնի թղթախաղ։ Ազատվեք բոլոր թղթերից. վերջինը, ում ձեռքին թղթեր մնան, դուրակն է։'],
            'poker' => ['ru' => 'Безлимитный техасский холдем на игровые фишки. Читайте соперников и собирайте лучшую комбинацию.', 'hy' => 'Առանց սահմանափակման Տեխասյան հոլդեմ՝ խաղային ֆիշկաներով։ Կարդացեք մրցակիցներին և հավաքեք լավագույն համակցությունը։'],
        ];

        foreach (DB::table('games')->whereIn('slug', array_keys($translations))->get(['id', 'slug', 'description']) as $game) {
            $description = json_decode($game->description, true, flags: JSON_THROW_ON_ERROR);
            $next = $description + $translations[$game->slug];
            if ($game->slug === 'poker' && str_contains($next['en'] ?? '', 'size your bets')) {
                $next['en'] = "No-Limit Texas Hold'em with play chips. Read your opponents and make the best hand.";
            }
            DB::table('games')->where('id', $game->id)->update(['description' => json_encode($next, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
        }
    }

    public function down(): void
    {
        // Descriptions are admin content: nothing to undo.
    }
};
