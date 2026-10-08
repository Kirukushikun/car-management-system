<?php

use App\Services\UserDirectory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

/**
 * Fixture copied from the real response pasted in the Authentication Implementation Guide
 * ("Response shape — verify against the REAL API"): bare array, split names, encrypted ids,
 * nullable created_at. Confirm against the live API with /admin/debug/user-api before go-live.
 *
 * @return array<string, mixed>
 */
function directoryRecord(int $id, string $first, string $last, string $email, array $overrides = []): array
{
    return [
        'id' => Crypt::encryptString((string) $id),
        'first_name' => $first,
        'last_name' => $last,
        'middle_name' => null,
        'email' => $email,
        'created_at' => null,
        'updated_at' => '2026-07-14T05:45:08.000000Z',
        ...$overrides,
    ];
}

beforeEach(function () {
    config(['services.user_api.endpoint' => 'https://auth.test/api/v1/users', 'services.user_api.key' => 'test-user-key']);
});

it('reads the bare-array response, decrypting ids and joining first and last names', function () {
    Http::fake(['auth.test/api/v1/users' => Http::response([
        directoryRecord(42, 'Maria Christina', 'Santos', 'm.santos@example.org'),
        directoryRecord(7, 'Andre', 'Capiz', 'a.capiz@example.org'),
    ])]);

    expect(app(UserDirectory::class)->all())->toBe([
        'users' => [
            ['id' => 7, 'name' => 'Andre Capiz', 'email' => 'a.capiz@example.org'],
            ['id' => 42, 'name' => 'Maria Christina Santos', 'email' => 'm.santos@example.org'],
        ],
        'undecryptable' => 0,
        'error' => null,
    ]);

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request->url() === 'https://auth.test/api/v1/users'
        && $request->hasHeader('x-api-key', 'test-user-key'));
});

it('also accepts a data wrapper and a single name field', function () {
    Http::fake(['auth.test/api/v1/users' => Http::response(['data' => [
        ['id' => Crypt::encryptString('9'), 'name' => 'Single Name', 'email' => 's@example.org'],
    ]])]);

    expect(app(UserDirectory::class)->all()['users'])->toBe([['id' => 9, 'name' => 'Single Name', 'email' => 's@example.org']]);
});

it('counts records whose id does not decrypt, the sign of an APP_KEY mismatch', function () {
    Http::fake(['auth.test/api/v1/users' => Http::response([
        directoryRecord(1, 'Good', 'Record', 'good@example.org'),
        directoryRecord(2, 'Foreign', 'Key', 'foreign@example.org', ['id' => 'eyJpdiI6ImZvcmVpZ24ifQ==']),
    ])]);

    expect(app(UserDirectory::class)->all())
        ->users->toHaveCount(1)
        ->undecryptable->toBe(1);
});

it('reports an error response or an unreachable API without caching it', function () {
    Http::fakeSequence('auth.test/*')
        ->push(['message' => 'down'], 503)
        ->pushFailedConnection()
        ->push([directoryRecord(3, 'Back', 'Online', 'back@example.org')]);

    $directory = app(UserDirectory::class);

    expect($directory->all()['error'])->toBe('The user directory answered with an error (HTTP 503).')
        ->and($directory->all()['error'])->toBe('The user directory is unreachable right now. Try Refresh in a moment.')
        ->and($directory->all()['users'])->toHaveCount(1);
});

it('caches a good listing for a minute and refetches after refresh', function () {
    Http::fake(['auth.test/api/v1/users' => Http::response([directoryRecord(5, 'Cached', 'Person', 'c@example.org')])]);
    $directory = app(UserDirectory::class);

    $directory->all();
    $directory->find(5);
    Http::assertSentCount(1);

    $directory->refresh();
    $directory->all();
    Http::assertSentCount(2);

    $this->travel(61)->seconds();
    $directory->all();
    Http::assertSentCount(3);
});

it('finds one person by central id', function () {
    Http::fake(['auth.test/api/v1/users' => Http::response([directoryRecord(5, 'Found', 'Person', 'f@example.org')])]);

    expect(app(UserDirectory::class)->find(5))->toBe(['id' => 5, 'name' => 'Found Person', 'email' => 'f@example.org'])
        ->and(app(UserDirectory::class)->find(6))->toBeNull();
});

it('does not call anything when the directory is not configured', function () {
    config(['services.user_api.endpoint' => '']);

    expect(app(UserDirectory::class)->all()['error'])->toBe('The user directory is not configured (USER_API_ENDPOINT).');
    Http::assertNothingSent();
});
