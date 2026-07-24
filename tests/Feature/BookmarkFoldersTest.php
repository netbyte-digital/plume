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

    $folders = X::bookmarkFolders('99')->data;

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

    expect(X::bookmarkFolders('99')->data)->toBe([]);
});

it('reads the post ids inside a folder', function (): void {
    Http::fake([
        'api.x.com/2/users/99/bookmarks/folders/555' => Http::response(['data' => [
            ['id' => '2057142315236618292'],
            ['id' => '2052396064024453218'],
        ]]),
    ]);

    expect(X::bookmarkFolder('99', '555')->data)->toBe(['2057142315236618292', '2052396064024453218']);
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

    $folder = X::bookmarkFolders('99')->data[0];

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

    expect(X::bookmarkFolders('99')->data[0]->posts())->toBe([]);

    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/2/tweets'));
});

it('resolves the user id automatically when scoped', function (): void {
    Http::fake([
        'api.x.com/2/users/me*' => Http::response(['data' => ['id' => '99', 'username' => 'me', 'name' => 'Me']]),
        'api.x.com/2/users/99/bookmarks/folders' => Http::response(['data' => [
            ['id' => '555', 'name' => 'Laravel'],
        ]]),
    ]);

    $folders = X::forUser(['access_token' => 'user-token'])->bookmarkFolders()->data;

    expect($folders)->toHaveCount(1)
        ->and($folders[0]->name)->toBe('Laravel');
});

it('throws a helpful error when the folder has no provider', function (): void {
    (new BookmarkFolder(id: '555', name: 'Orphan'))->postIds();
})->throws(LogicException::class, 'requires an XApiProvider');

it('follows pagination when X returns a next_token', function (): void {
    Http::fake([
        'api.x.com/2/users/99/bookmarks/folders*' => Http::sequence()
            ->push(['data' => [['id' => '1', 'name' => 'Page one']], 'meta' => ['next_token' => 'tok2']])
            ->push(['data' => [['id' => '2', 'name' => 'Page two']]]),
    ]);

    $page = X::bookmarkFolders('99', 1);

    expect($page->hasNextPage())->toBeTrue()
        ->and($page->data[0]->name)->toBe('Page one');

    $next = $page->nextPage();

    expect($next->data[0]->name)->toBe('Page two')
        ->and($next->hasNextPage())->toBeFalse();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'pagination_token=tok2'));
});

it('sends max_results when given', function (): void {
    Http::fake(['api.x.com/2/users/99/bookmarks/folders*' => Http::response(['data' => []])]);

    X::bookmarkFolders('99', 100);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'max_results=100'));
});

it('follows pagination when collecting all folder post ids', function (): void {
    Http::fake([
        'api.x.com/2/users/99/bookmarks/folders' => Http::response(['data' => [['id' => '555', 'name' => 'Big']]]),
        'api.x.com/2/users/99/bookmarks/folders/555*' => Http::sequence()
            ->push(['data' => [['id' => '111']], 'meta' => ['next_token' => 'tok2']])
            ->push(['data' => [['id' => '222']]]),
    ]);

    expect(X::bookmarkFolders('99')->data[0]->postIds())->toBe(['111', '222']);
});
