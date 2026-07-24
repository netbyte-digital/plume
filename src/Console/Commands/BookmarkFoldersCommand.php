<?php

declare(strict_types=1);

namespace Plume\Console\Commands;

use Illuminate\Console\Command;
use Plume\Console\Concerns\ResolvesXClient;
use Plume\Console\Concerns\SupportsJsonOutput;

class BookmarkFoldersCommand extends Command
{
    use ResolvesXClient;
    use SupportsJsonOutput;

    /** @var string */
    protected $signature = 'plume:bookmark-folders {--format=table}';

    /** @var string */
    protected $description = 'List your bookmark folders';

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

        try {
            $folders = $client->bookmarkFolders($userId);
        } catch (\Throwable $e) {
            $this->error("Failed: {$e->getMessage()}");

            return self::FAILURE;
        }

        if ($this->outputJson($folders)) {
            return self::SUCCESS;
        }

        if ($folders === []) {
            $this->info('No bookmark folders found.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Name'],
            array_map(fn ($folder) => [$folder->id, $folder->name], $folders),
        );
        $this->info(count($folders).' folder(s) found.');

        return self::SUCCESS;
    }
}
