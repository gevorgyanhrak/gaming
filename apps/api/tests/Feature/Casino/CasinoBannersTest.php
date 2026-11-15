<?php

use App\Filament\Resources\CasinoBanners\CasinoBannerResource;
use App\Filament\Resources\CasinoBanners\Pages\CreateCasinoBanner;
use App\Models\AuditLog;
use App\Models\CasinoBanner;
use Livewire\Livewire;

beforeEach(function () {
    config(['platform.supported_locales' => ['en', 'ru', 'hy'], 'app.fallback_locale' => 'en']);
});

it('lists the visible banners inside their date window, in order and translated', function () {
    CasinoBanner::query()->create(['title' => ['en' => 'Second', 'ru' => 'Второй'], 'sort_order' => 20, 'link' => '/games/poker']);
    CasinoBanner::query()->create(['title' => ['en' => 'First'], 'cta' => ['en' => 'Play'], 'sort_order' => 10]);
    CasinoBanner::query()->create(['title' => ['en' => 'Hidden'], 'is_visible' => false]);
    CasinoBanner::query()->create(['title' => ['en' => 'Later'], 'starts_at' => now()->addDay()]);
    CasinoBanner::query()->create(['title' => ['en' => 'Over'], 'ends_at' => now()->subMinute()]);
    CasinoBanner::query()->create(['title' => ['en' => 'Now'], 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(), 'sort_order' => 30]);

    $this->getJson('/api/v1/banners', ['Accept-Language' => 'ru'])
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.title', 'First')
        ->assertJsonPath('data.0.cta', 'Play')
        ->assertJsonPath('data.0.link', null)
        ->assertJsonPath('data.1.title', 'Второй')
        ->assertJsonPath('data.1.link', '/games/poker')
        ->assertJsonPath('data.2.title', 'Now');
});

it('lets an admin create a banner, audited; refuses a link off the site that is not https', function () {
    signInToAdmin('admin');

    Livewire::test(CreateCasinoBanner::class)
        ->fillForm(['title' => ['en' => 'Poker night', 'ru' => '', 'hy' => ''], 'link' => '//evil.example'])
        ->call('create')
        ->assertHasFormErrors(['link']);
    Livewire::test(CreateCasinoBanner::class)
        ->fillForm(['title' => ['en' => 'Poker night', 'ru' => '', 'hy' => ''], 'link' => 'javascript:alert(1)'])
        ->call('create')
        ->assertHasFormErrors(['link']);

    Livewire::test(CreateCasinoBanner::class)
        ->fillForm(['title' => ['en' => 'Poker night', 'ru' => '', 'hy' => ''], 'link' => '/games/poker'])
        ->call('create')
        ->assertHasNoFormErrors();

    $banner = CasinoBanner::query()->sole();
    expect($banner->getTranslations('title'))->toBe(['en' => 'Poker night'])
        ->and($banner->is_visible)->toBeTrue()
        ->and(AuditLog::query()->where('action', 'like', '%created')->where('changes', 'like', '%Poker night%')->exists())->toBeTrue();

    signInToAdmin('moderator');
    $this->get(CasinoBannerResource::getUrl('index'))->assertForbidden();
});
