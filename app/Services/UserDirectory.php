<?php

namespace App\Services;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The central user directory (USER_API_ENDPOINT) — everyone in the organization, so the IT Admin
 * can grant access to this system (Authentication Implementation Guide, Part 2).
 *
 * Response shape per the guide's pasted real response: a bare array (a `data` wrapper is also
 * accepted), split first/middle/last names, and ids encrypted with the shared APP_KEY. Records
 * whose id cannot be decrypted are counted, not shown — a non-zero count means this system's
 * APP_KEY differs from the central system's.
 *
 * The list is cached for 60 seconds so searching does not hit the API on every keystroke.
 */
class UserDirectory
{
    private const CACHE_KEY = 'user_directory';

    private const CACHE_SECONDS = 60;

    /**
     * @return array{users: list<array{id: int, name: string, email: string}>, undecryptable: int, error: ?string}
     */
    public function all(): array
    {
        $cached = Cache::get(self::CACHE_KEY);

        if ($cached !== null) {
            return $cached;
        }

        $result = $this->fetch();

        if ($result['error'] === null) {
            Cache::put(self::CACHE_KEY, $result, self::CACHE_SECONDS);
        }

        return $result;
    }

    /**
     * One person by central user id, from the (cached) directory — never from what the browser sent.
     *
     * @return array{id: int, name: string, email: string}|null
     */
    public function find(int $id): ?array
    {
        return collect($this->all()['users'])->firstWhere('id', $id);
    }

    public function refresh(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array{users: list<array{id: int, name: string, email: string}>, undecryptable: int, error: ?string}
     */
    private function fetch(): array
    {
        if (blank(config('services.user_api.endpoint'))) {
            return $this->failed('The user directory is not configured (USER_API_ENDPOINT).');
        }

        try {
            $response = Http::withHeaders(['x-api-key' => (string) config('services.user_api.key')])
                ->acceptJson()
                ->withOptions(['verify' => storage_path('cacert.pem')])
                ->timeout(10)
                ->connectTimeout(5)
                ->post((string) config('services.user_api.endpoint'));
        } catch (Throwable $e) {
            Log::warning('User directory API unreachable', ['error' => $e->getMessage()]);

            return $this->failed('The user directory is unreachable right now. Try Refresh in a moment.');
        }

        if (! $response->successful()) {
            Log::warning('User directory API error', ['status' => $response->status()]);

            return $this->failed("The user directory answered with an error (HTTP {$response->status()}).");
        }

        $json = $response->json();
        $records = is_array($json) ? ($json['data'] ?? $json) : [];

        $users = [];
        $undecryptable = 0;

        foreach (is_array($records) ? $records : [] as $record) {
            try {
                $id = Crypt::decryptString((string) ($record['id'] ?? ''));
            } catch (DecryptException) {
                $undecryptable++;

                continue;
            }

            if (! ctype_digit($id)) {
                $undecryptable++;

                continue;
            }

            $users[] = [
                'id' => (int) $id,
                'name' => trim(($record['first_name'] ?? '').' '.($record['last_name'] ?? '')) ?: (string) ($record['name'] ?? ''),
                'email' => (string) ($record['email'] ?? ''),
            ];
        }

        usort($users, fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

        return ['users' => $users, 'undecryptable' => $undecryptable, 'error' => null];
    }

    /**
     * @return array{users: list<array{id: int, name: string, email: string}>, undecryptable: int, error: string}
     */
    private function failed(string $message): array
    {
        return ['users' => [], 'undecryptable' => 0, 'error' => $message];
    }
}
