<?php

use App\Providers\AppServiceProvider;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Route;

afterEach(fn () => TrustProxies::flushState());

it('trusts forwarding headers only from configured proxies', function (?string $setting, string $expected) {
    config(['platform.trusted_proxies' => $setting]);
    (new AppServiceProvider(app()))->boot();
    Route::get('/test/ip', fn () => request()->ip());

    $this->get('/test/ip', ['X-Forwarded-For' => '203.0.113.7'])->assertSee($expected);
})->with([
    'not configured' => [null, '127.0.0.1'],
    'the proxy' => ['127.0.0.1', '203.0.113.7'],
]);
