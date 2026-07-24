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
     * X does not paginate this endpoint, so every folder is returned at once.
     *
     * @return list<BookmarkFolder>
     */
    public function bookmarkFolders(string $userId): array
    {
        $response = $this->http->get("/2/users/{$userId}/bookmarks/folders");

        return array_values(array_map(
            fn (array $folder): BookmarkFolder => $this->mapBookmarkFolder($folder, $userId),
            $response['data'] ?? [],
        ));
    }

    /**
     * The IDs of the posts filed under a bookmark folder.
     *
     * This endpoint returns identifiers only — no post bodies, and no
     * pagination. Use getPosts() to hydrate them.
     *
     * @return list<string>
     */
    public function bookmarkFolder(string $userId, string $folderId): array
    {
        $response = $this->http->get("/2/users/{$userId}/bookmarks/folders/{$folderId}");

        return array_values(array_filter(array_map(
            fn (array $post): ?string => isset($post['id']) ? (string) $post['id'] : null,
            $response['data'] ?? [],
        )));
    }
}
