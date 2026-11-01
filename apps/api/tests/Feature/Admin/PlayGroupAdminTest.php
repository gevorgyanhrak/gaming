<?php

use App\Enums\PlayGroupKind;
use App\Filament\Resources\PlayGroups\Pages\ManagePlayGroups;
use App\Models\AuditLog;
use App\Models\PlayGroup;
use Livewire\Livewire;

it('lets staff change a group’s limits, audited, and nothing else', function () {
    $admin = signInToAdmin('super_admin');
    $casual = PlayGroup::query()->where('kind', PlayGroupKind::Stake)->where('slug', 'casual')->sole();

    Livewire::test(ManagePlayGroups::class)
        ->assertCanSeeTableRecords(PlayGroup::all())
        ->callTableAction('edit', $casual, data: ['min_balance' => 6_000, 'min_bet' => 2_500, 'max_bet' => 12_000, 'is_enabled' => true])
        ->assertHasNoTableActionErrors();

    expect($casual->refresh()->only(['min_balance', 'min_bet', 'max_bet']))->toBe(['min_balance' => 6_000, 'min_bet' => 2_500, 'max_bet' => 12_000])
        ->and(AuditLog::query()->where('action', 'play_group.updated')->sole()->actor_id)->toBe($admin->id);
});

it('refuses a maximum bet below the minimum', function () {
    signInToAdmin('super_admin');
    $casual = PlayGroup::query()->where('kind', PlayGroupKind::Stake)->where('slug', 'casual')->sole();

    Livewire::test(ManagePlayGroups::class)
        ->callTableAction('edit', $casual, data: ['min_balance' => 5_000, 'min_bet' => 2_000, 'max_bet' => 1_000, 'is_enabled' => true])
        ->assertHasTableActionErrors(['max_bet']);

    expect($casual->refresh()->max_bet)->toBe(10_000);
});
