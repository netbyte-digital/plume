<?php

declare(strict_types=1);

namespace Plume\Console\Concerns;

use Plume\Contracts\HasXCredentials;
use Plume\Contracts\XApiProvider;
use Plume\XApiClient;

trait ResolvesXClient
{
    /**
     * Resolve an authenticated X API client.
     *
     * OAuth 2.0 user context credentials take precedence when configured, since
     * endpoints such as bookmarks, likes and follows reject app-only tokens.
     * Otherwise falls back to the app-only bearer token.
     *
     * Returns the XApiProvider instance or FAILURE if not configured.
     */
    protected function resolveClient(): XApiProvider|int
    {
        $client = app(XApiProvider::class);
        $credentials = $this->resolveUserCredentials();

        if ($credentials !== null) {
            return $client instanceof XApiClient
                ? $client->withUserCredentials($credentials)
                : $client;
        }

        $bearerToken = config('x.bearer_token');

        if (! is_string($bearerToken) || $bearerToken === '') {
            $this->error('No X API bearer token configured. Set X_BEARER_TOKEN in your .env file.');
            $this->line('Endpoints needing user context also accept x.access_token or a bound "x.credentials".');

            return self::FAILURE;
        }

        return $client;
    }

    /**
     * Resolve OAuth 2.0 user context credentials, if any are configured.
     *
     * Applications that store tokens outside of config — in a database, for
     * example — may bind "x.credentials" to a HasXCredentials instance or to a
     * credentials array.
     *
     * @return HasXCredentials|array<string, string|null>|null
     */
    protected function resolveUserCredentials(): HasXCredentials|array|null
    {
        if (app()->bound('x.credentials')) {
            $bound = app()->make('x.credentials');

            if ($bound instanceof HasXCredentials) {
                return $bound;
            }

            if (is_array($bound) && isset($bound['access_token'])) {
                return $bound;
            }
        }

        $accessToken = config('x.access_token');

        if (! is_string($accessToken) || $accessToken === '') {
            return null;
        }

        $refreshToken = config('x.refresh_token');
        $expiresAt = config('x.expires_at');

        return [
            'access_token' => $accessToken,
            'refresh_token' => is_string($refreshToken) ? $refreshToken : null,
            'expires_at' => is_string($expiresAt) ? $expiresAt : null,
        ];
    }

    /**
     * Resolve the authenticated user's ID.
     *
     * Calls /me to get the current user. Returns the user ID or FAILURE.
     */
    protected function resolveUserId(XApiProvider $client): string|int
    {
        try {
            return $client->me()->id;
        } catch (\Throwable $e) {
            $this->error("Failed to resolve user identity: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
