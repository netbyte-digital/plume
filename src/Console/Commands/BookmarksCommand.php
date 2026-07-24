<?php

declare(strict_types=1);

namespace Plume\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Plume\Console\Concerns\ResolvesXClient;
use Plume\Console\Concerns\SupportsJsonOutput;

class BookmarksCommand extends Command
{
    use ResolvesXClient;
    use SupportsJsonOutput;

    /** @var string */
    protected $signature = 'plume:bookmarks {--max-results=10} {--folder= : Only show bookmarks in this folder ID} {--format=table}';

    /** @var string */
    protected $description = 'List your bookmarked tweets';

    public function handle(): int
    {
        $client = $this->resolveClient();

        if (is_int($client)) {
            return $client;
        }

        $userId = $this->resolveUserId($client);

        if (is_int($userId)) {
            return $userId;
        }

        $maxResults = (int) $this->option('max-results');
        $folderId = $this->option('folder');
        $inFolder = is_string($folderId) && $folderId !== '';

        try {
            if ($inFolder) {
                // The folder endpoint returns post IDs only, so they are
                // trimmed to the requested count and hydrated separately.
                $page = $client->bookmarkFolder($userId, $folderId, $maxResults);
                $postIds = $page->data;

                while (count($postIds) < $maxResults && $page->hasNextPage()) {
                    $page = $page->nextPage();

                    if ($page === null) {
                        break;
                    }

                    $postIds = [...$postIds, ...$page->data];
                }

                $payload = $postIds === []
                    ? []
                    : $client->getPosts(array_slice($postIds, 0, $maxResults));
            } else {
                $payload = $client->bookmarks($userId, maxResults: $maxResults);
            }
        } catch (\Throwable $e) {
            $this->error("Failed: {$e->getMessage()}");

            return self::FAILURE;
        }

        if ($this->outputJson($payload)) {
            return self::SUCCESS;
        }

        $posts = $inFolder ? $payload : $payload->data;

        if (count($posts) === 0) {
            $this->info('No bookmarked tweets found.');

            return self::SUCCESS;
        }

        $rows = array_map(fn ($post) => [
            $post->id,
            Str::limit($post->text, 80),
            $post->createdAt ?? 'N/A',
        ], $posts);

        $this->table(['ID', 'Text', 'Created At'], $rows);
        $this->info(count($posts).' item(s) found.');

        return self::SUCCESS;
    }
}
