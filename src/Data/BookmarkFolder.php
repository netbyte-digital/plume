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
     * The IDs of the posts filed under this folder.
     *
     * X returns identifiers only. Hydrate them with getPosts() when the post
     * bodies are needed.
     *
     * @return list<string>
     */
    public function postIds(): array
    {
        return $this->provider()->bookmarkFolder($this->userId(), $this->id);
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
