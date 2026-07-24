<?php

declare(strict_types=1);

namespace Plume\Data;

use Plume\Contracts\XApiProvider;
use Plume\Enums\Expansion;
use Plume\Enums\MediaField;
use Plume\Enums\TweetField;
use Plume\Enums\UserField;

class BookmarkFolder
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        protected ?string $userId = null,
        protected ?XApiProvider $provider = null,
    ) {}

    public function withProvider(XApiProvider $provider, ?string $userId = null): static
    {
        $clone = clone $this;
        $clone->provider = $provider;
        $clone->userId = $userId ?? $this->userId;

        return $clone;
    }

    /**
     * A page of post IDs filed under this folder.
     *
     * X returns identifiers only. Hydrate them with getPosts() when the post
     * bodies are needed.
     *
     * @return PaginatedResult<string>
     */
    public function postIdsPaginated(?int $maxResults = null, ?string $paginationToken = null): PaginatedResult
    {
        return $this->provider()->bookmarkFolder($this->userId(), $this->id, $maxResults, $paginationToken);
    }

    /**
     * Every post ID filed under this folder, following pagination to the end.
     *
     * @return list<string>
     */
    public function postIds(?int $maxResults = null): array
    {
        $page = $this->postIdsPaginated($maxResults);
        $ids = $page->data;

        while ($page->hasNextPage()) {
            $page = $page->nextPage();

            if ($page === null) {
                break;
            }

            $ids = [...$ids, ...$page->data];
        }

        return array_values($ids);
    }

    /**
     * The posts filed under this folder, hydrated in a second request.
     *
     * @param  list<TweetField>  $tweetFields
     * @param  list<Expansion>  $expansions
     * @param  list<UserField>  $userFields
     * @param  list<MediaField>  $mediaFields
     * @return list<Post>
     */
    public function posts(
        array $tweetFields = [],
        array $expansions = [],
        array $userFields = [],
        array $mediaFields = [],
    ): array {
        $ids = $this->postIds();

        if ($ids === []) {
            return [];
        }

        return $this->provider()->getPosts($ids, $tweetFields, $expansions, $userFields, $mediaFields);
    }

    protected function provider(): XApiProvider
    {
        return $this->provider ?? throw new \LogicException('BookmarkFolder requires an XApiProvider. Call withProvider() first.');
    }

    protected function userId(): string
    {
        return $this->userId ?? throw new \LogicException('BookmarkFolder requires a user ID. Call withProvider() with one.');
    }
}
