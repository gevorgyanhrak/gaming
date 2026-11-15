<?php

use App\Filament\Resources\SlotGames\Pages\CreateSlotGame;
use App\Filament\Resources\SlotProviders\Pages\CreateSlotProvider;
use App\Filament\Resources\SlotProviders\Pages\EditSlotProvider;
use App\Filament\Resources\SlotTransactions\SlotTransactionResource;
use App\Models\AuditLog;
use App\Models\SlotGame;
use App\Models\SlotProvider;
use Livewire\Livewire;
use Tests\Fixtures\Slots\FakeSlotAdapter;

it('keeps a provider without an adapter switched off', function () {
    signInToAdmin('admin');

    Livewire::test(CreateSlotProvider::class)
        ->fillForm(['name' => 'Someone', 'slug' => 'someone', 'is_enabled' => true])
        ->call('create')
        ->assertHasFormErrors(['is_enabled']);

    Livewire::test(CreateSlotProvider::class)
        ->fillForm(['name' => 'Someone', 'slug' => 'someone', 'is_enabled' => false])
        ->call('create')
        ->assertHasNoFormErrors();
    expect(AuditLog::query()->where('action', 'like', 'slot_provider.created')->exists())->toBeTrue();
});

it('switches on a connected provider and lists its game with bet limits', function () {
    app()->tag([FakeSlotAdapter::class], 'slot-adapters');
    signInToAdmin('admin');
    $provider = SlotProvider::query()->create(['slug' => 'fakeslots', 'name' => 'Fake Slots']);

    Livewire::test(EditSlotProvider::class, ['record' => $provider->getRouteKey()])
        ->fillForm(['is_enabled' => true])
        ->call('save')
        ->assertHasNoFormErrors();
    expect($provider->fresh()->is_enabled)->toBeTrue();

    Livewire::test(CreateSlotGame::class)
        ->fillForm([
            'slot_provider_id' => $provider->id, 'provider_game_id' => 'fruit-5', 'slug' => 'fruit',
            'name' => ['en' => 'Fruit', 'ru' => '', 'hy' => ''], 'min_bet' => 50, 'max_bet' => 10, 'max_multiplier' => 500,
        ])
        ->call('create')
        ->assertHasFormErrors(['max_bet']);

    Livewire::test(CreateSlotGame::class)
        ->fillForm([
            'slot_provider_id' => $provider->id, 'provider_game_id' => 'fruit-5', 'slug' => 'fruit',
            'name' => ['en' => 'Fruit', 'ru' => '', 'hy' => ''], 'min_bet' => 10, 'max_bet' => 500, 'max_multiplier' => 500, 'is_enabled' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->getJson('/api/v1/slots')->assertOk()->assertJsonPath('data.0.slug', 'fruit')->assertJsonPath('data.0.max_bet', 500);
    expect(SlotGame::query()->sole()->getTranslations('name'))->toBe(['en' => 'Fruit']);
});

it('shows slot wallet calls to game managers only', function () {
    signInToAdmin('admin');
    $this->get(SlotTransactionResource::getUrl('index'))->assertOk();

    signInToAdmin('moderator');
    $this->get(SlotTransactionResource::getUrl('index'))->assertForbidden();
});
