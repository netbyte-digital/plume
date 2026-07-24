<?php

declare(strict_types=1);

namespace Plume\Concerns;

use Plume\Data\BookmarkFolder;
use Plume\Data\PaginatedResult;
use Plume\Data\Post;
use Plume\Enums\Expansion;
use Plume\Enums\MediaField;
use Plume\Enums\TweetField;
use Plume\Enums\UserField;

trait ManagesBookmarks
{
    public function bookmark(string $userId, string $tweetId): void
    {
        $this->http->post("/2/users/{$userId}/bookmarks", [
            'tweet_id' => $tweetId,
        ]);
    }

    public function removeBookmark(string $userId, string $tweetId): void
    {
        $this->http->delete("/2/users/{$userId}/bookmarks/{$tweetId}");
    }

    /**
     * @param  list<TweetField>  $tweetFields
     * @param  list<Expansion>  $expansions
     * @param  list<UserField>  $userFields
     * @param  list<MediaField>  $mediaFields
     * @return PaginatedResult<Post>
     */
    public function bookmarks(
        string $userId,
        int $maxResults = 100,
        ?string $paginationToken = null,
        array $tweetFields = [],
        array $expansions = [],
        array $userFields = [],
        array $mediaFields = [],
    ): PaginatedResult {
        $query = array_merge(
            ['max_results' => $maxResults],
            $this->buildFieldQuery($tweetFields, $expansions, $userFields, $mediaFields),
        );

        if ($paginationToken !== null) {
            $query['pagination_token'] = $paginationToken;
        }

        $response = $this->http->get("/2/users/{$userId}/bookmarks", $query);

        return $this->paginatedPosts($response, fn (string $token): PaginatedResult => $this->bookmarks(
            $userId, $maxResults, $token, $tweetFields, $expansions, $userFields, $mediaFields,
        ));
    }

    /**
     * The user's bookmark folders.
     *
     * @return PaginatedResult<BookmarkFolder>
     */
    public function bookmarkFolders(
        string $userId,
        ?int $maxResults = null,
        ?string $paginationToken = null,
    ): PaginatedResult {
        $query = [];

        if ($maxResults !== null) {
            $query['max_results'] = $maxResults;
        }

        if ($paginationToken !== null) {
            $query['pagination_token'] = $paginationToken;
        }

        $response = $this->http->get("/2/users/{$userId}/bookmarks/folders", $query);

        return $this->paginatedBookmarkFolders(
            $response,
            $userId,
            fn (string $token): PaginatedResult => $this->bookmarkFolders($userId, $maxResults, $token),
        );
    }

    /**
     * The IDs of the posts filed under a bookmark folder.
     *
     * This endpoint returns identifiers only, not post bodies. Use getPosts()
     * to hydrate them.
     *
     * @return PaginatedResult<string>
     */
    public function bookmarkFolder(
        string $userId,
        string $folderId,
        ?int $maxResults = null,
        ?string $paginationToken = null,
    ): PaginatedResult {
        $query = [];

        if ($maxResults !== null) {
            $query['max_results'] = $maxResults;
        }

        if ($paginationToken !== null) {
            $query['pagination_token'] = $paginationToken;
        }

        $response = $this->http->get("/2/users/{$userId}/bookmarks/folders/{$folderId}", $query);

        /** @var array<int, array<string, mixed>> $items */
        $items = $response['data'] ?? [];

        /** @var list<string> $postIds */
        $postIds = [];

        foreach ($items as $post) {
            if (isset($post['id'])) {
                $postIds[] = (string) $post['id'];
            }
        }

        $result = new PaginatedResult(
            data: $postIds,
            nextToken: $response['meta']['next_token'] ?? null,
            previousToken: $response['meta']['previous_token'] ?? null,
            resultCount: $response['meta']['result_count'] ?? count($postIds),
        );

        return $result->withNextPageCallback(
            fn (string $token): PaginatedResult => $this->bookmarkFolder($userId, $folderId, $maxResults, $token),
        );
    }
}
