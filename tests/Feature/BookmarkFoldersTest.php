<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Plume\Data\BookmarkFolder;
use Plume\Facades\X;

beforeEach(function (): void {
    config(['x.bearer_token' => 'test-token']);
});

it('lists bookmark folders', function (): void {
    Http::fake([
        'api.x.com/2/users/99/bookmarks/folders' => Http::response(['data' => [
            ['id' => '1904047739266281865', 'name' => 'AI'],
            ['id' => '2027122003057295699', 'name' => 'Laravel'],
        ]]),
    ]);

    $folders = X::bookmarkFolders('99');

    expect($folders)->toHaveCount(2)
        ->and($folders[0])->toBeInstanceOf(BookmarkFolder::class)
        ->and($folders[0]->id)->toBe('1904047739266281865')
        ->and($folders[0]->name)->toBe('AI')
        ->and($folders[1]->name)->toBe('Laravel');
});

it('returns an empty list when there are no folders', function (): void {
    Http::fake([
        'api.x.com/2/users/99/bookmarks/folders' => Http::response([]),
    ]);

    expect(X::bookmarkFolders('99'))->toBe([]);
});

it('reads the post ids inside a folder', function (): void {
    Http::fake([
        'api.x.com/2/users/99/bookmarks/folders/555' => Http::response(['data' => [
            ['id' => '2057142315236618292'],
            ['id' => '2052396064024453218'],
        ]]),
    ]);

    expect(X::bookmarkFolder('99', '555'))->toBe(['2057142315236618292', '2052396064024453218']);
});

it('hydrates folder posts through a second request', function (): void {
    Http::fake([
        'api.x.com/2/users/99/bookmarks/folders' => Http::response(['data' => [
            ['id' => '555', 'name' => 'Laravel'],
        ]]),
        'api.x.com/2/users/99/bookmarks/folders/555' => Http::response(['data' => [
            ['id' => '111'],
            ['id' => '222'],
        ]]),
        'api.x.com/2/tweets*' => Http::response(['data' => [
            ['id' => '111', 'text' => 'first post'],
            ['id' => '222', 'text' => 'second post'],
        ]]),
    ]);

    $folder = X::bookmarkFolders('99')[0];

    expect($folder->postIds())->toBe(['111', '222']);

    $posts = $folder->posts();

    expect($posts)->toHaveCount(2)
        ->and($posts[0]->text)->toBe('first post');
});

it('does not call the posts endpoint for an empty folder', function (): void {
    Http::fake([
        'api.x.com/2/users/99/bookmarks/folders' => Http::response(['data' => [
            ['id' => '555', 'name' => 'Empty'],
        ]]),
        'api.x.com/2/users/99/bookmarks/folders/555' => Http::response(['data' => []]),
    ]);

    expect(X::bookmarkFolders('99')[0]->posts())->toBe([]);

    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/2/tweets'));
});

it('resolves the user id automatically when scoped', function (): void {
    Http::fake([
        'api.x.com/2/users/me*' => Http::response(['data' => ['id' => '99', 'username' => 'me', 'name' => 'Me']]),
        'api.x.com/2/users/99/bookmarks/folders' => Http::response(['data' => [
            ['id' => '555', 'name' => 'Laravel'],
        ]]),
    ]);

    $folders = X::forUser(['access_token' => 'user-token'])->bookmarkFolders();

    expect($folders)->toHaveCount(1)
        ->and($folders[0]->name)->toBe('Laravel');
});

it('throws a helpful error when the folder has no provider', function (): void {
    (new BookmarkFolder(id: '555', name: 'Orphan'))->postIds();
})->throws(LogicException::class, 'requires an XApiProvider');
