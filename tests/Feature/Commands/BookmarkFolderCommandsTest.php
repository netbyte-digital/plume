<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config(['x.bearer_token' => 'test-token', 'x.access_token' => null]);

    Http::fake([
        'api.x.com/2/users/me*' => Http::response(['data' => ['id' => '99', 'username' => 'me', 'name' => 'Me']]),
        'api.x.com/2/users/99/bookmarks/folders' => Http::response(['data' => [
            ['id' => '555', 'name' => 'Laravel'],
            ['id' => '666', 'name' => 'Design'],
        ]]),
        'api.x.com/2/users/99/bookmarks/folders/555*' => Http::response(['data' => [
            ['id' => '111'], ['id' => '222'], ['id' => '333'],
        ]]),
        'api.x.com/2/tweets*' => Http::response(['data' => [
            ['id' => '111', 'text' => 'a laravel post'],
            ['id' => '222', 'text' => 'another laravel post'],
        ]]),
    ]);
});

it('lists bookmark folders', function (): void {
    $this->artisan('plume:bookmark-folders')
        ->assertSuccessful()
        ->expectsOutputToContain('Laravel')
        ->expectsOutputToContain('Design')
        ->expectsOutputToContain('2 folder(s) found.');
});

it('outputs folders as json', function (): void {
    $this->artisan('plume:bookmark-folders', ['--format' => 'json'])
        ->assertSuccessful()
        ->expectsOutputToContain('"name": "Laravel"');
});

it('filters bookmarks by folder', function (): void {
    $this->artisan('plume:bookmarks', ['--folder' => '555'])
        ->assertSuccessful()
        ->expectsOutputToContain('a laravel post');

    Http::assertSent(fn ($request) => str_contains($request->url(), '/bookmarks/folders/555'));
});

it('trims folder results to max-results client side', function (): void {
    $this->artisan('plume:bookmarks', ['--folder' => '555', '--max-results' => 2])
        ->assertSuccessful();

    // Only the first two of the three IDs should be hydrated.
    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/2/tweets')) {
            return false;
        }

        return str_contains($request->url(), 'ids=111%2C222')
            || str_contains($request->url(), 'ids=111,222');
    });
});

it('reports an empty folder without hydrating posts', function (): void {
    Http::fake([
        'api.x.com/2/users/me*' => Http::response(['data' => ['id' => '99', 'username' => 'me', 'name' => 'Me']]),
        'api.x.com/2/users/99/bookmarks/folders/777*' => Http::response(['data' => []]),
    ]);

    $this->artisan('plume:bookmarks', ['--folder' => '777'])
        ->assertSuccessful()
        ->expectsOutputToContain('No bookmarked tweets found.');
});

it('still lists all bookmarks when no folder is given', function (): void {
    Http::fake([
        'api.x.com/2/users/me*' => Http::response(['data' => ['id' => '99', 'username' => 'me', 'name' => 'Me']]),
        'api.x.com/2/users/99/bookmarks*' => Http::response(['data' => [
            ['id' => '999', 'text' => 'an unfiled bookmark'],
        ]]),
    ]);

    $this->artisan('plume:bookmarks')
        ->assertSuccessful()
        ->expectsOutputToContain('an unfiled bookmark');
});
