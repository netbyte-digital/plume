<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Plume\Contracts\HasXCredentials;

beforeEach(function (): void {
    config([
        'x.bearer_token' => null,
        'x.access_token' => null,
        'x.refresh_token' => null,
        'x.expires_at' => null,
    ]);
});

function fakeMeAndBookmarks(): void
{
    Http::fake([
        'api.x.com/2/users/me*' => Http::response(['data' => ['id' => '99', 'username' => 'someone', 'name' => 'Someone']]),
        'api.x.com/2/users/99/bookmarks*' => Http::response(['data' => [
            ['id' => '1', 'text' => 'a bookmarked post'],
        ]]),
    ]);
}

it('authenticates as the user when an access token is configured', function (): void {
    config(['x.access_token' => 'user-access-token']);

    fakeMeAndBookmarks();

    $this->artisan('plume:bookmarks')->assertSuccessful();

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer user-access-token'));
});

it('prefers user context over the app-only bearer token', function (): void {
    config([
        'x.bearer_token' => 'app-only-token',
        'x.access_token' => 'user-access-token',
    ]);

    fakeMeAndBookmarks();

    $this->artisan('plume:bookmarks')->assertSuccessful();

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer user-access-token'));
    Http::assertNotSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer app-only-token'));
});

it('resolves credentials bound to the container', function (): void {
    app()->instance('x.credentials', new class implements HasXCredentials
    {
        public function toXCredentials(): array
        {
            return ['access_token' => 'bound-token', 'refresh_token' => null, 'expires_at' => null];
        }
    });

    fakeMeAndBookmarks();

    $this->artisan('plume:bookmarks')->assertSuccessful();

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer bound-token'));
});

it('lets bound credentials take precedence over configured ones', function (): void {
    config(['x.access_token' => 'config-token']);
    app()->instance('x.credentials', ['access_token' => 'bound-token']);

    fakeMeAndBookmarks();

    $this->artisan('plume:bookmarks')->assertSuccessful();

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer bound-token'));
});

it('still uses the app-only bearer token when no user context exists', function (): void {
    config(['x.bearer_token' => 'app-only-token']);

    Http::fake([
        'api.x.com/2/tweets/search/recent*' => Http::response(['data' => []]),
    ]);

    $this->artisan('plume:search', ['query' => 'laravel'])->assertSuccessful();

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer app-only-token'));
});

it('ignores an empty access token and falls back to the bearer token', function (): void {
    config([
        'x.bearer_token' => 'app-only-token',
        'x.access_token' => '',
    ]);

    Http::fake([
        'api.x.com/2/tweets/search/recent*' => Http::response(['data' => []]),
    ]);

    $this->artisan('plume:search', ['query' => 'laravel'])->assertSuccessful();

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer app-only-token'));
});

it('mentions both authentication options when nothing is configured', function (): void {
    $this->artisan('plume:bookmarks')
        ->assertFailed()
        ->expectsOutputToContain('No X API bearer token configured')
        ->expectsOutputToContain('x.access_token');
});
