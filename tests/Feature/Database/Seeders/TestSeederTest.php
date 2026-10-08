<?php

use App\Models\Car;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\TestSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('local'));

it('creates sample accounts flagged and signed with the shared sample password', function () {
    $this->seed([ReferenceDataSeeder::class, TestSeeder::class]);

    $users = User::all();

    expect($users)->not->toBeEmpty()
        ->and($users->every(fn (User $user): bool => $user->is_sample))->toBeTrue()
        ->and(Hash::check(config('login.sample_password'), User::where('email', 'gab.maglalang@car.test')->sole()->password))->toBeTrue()
        ->and(Car::count())->toBe(10);
});

/**
 * Run a seeder directly — `db:seed` would stop to ask for confirmation in production.
 */
function runSeeder(string $class): void
{
    app($class)->setContainer(app())->__invoke();
}

it('refuses to run in production', function () {
    $this->seed(ReferenceDataSeeder::class);
    app()->detectEnvironment(fn (): string => 'production');

    expect(fn () => runSeeder(TestSeeder::class))->toThrow(RuntimeException::class, 'must never run in production');
    expect(User::count())->toBe(0);
});

it('seeds only reference data in production', function () {
    app()->detectEnvironment(fn (): string => 'production');

    runSeeder(DatabaseSeeder::class);

    expect(User::count())->toBe(0)
        ->and(Car::count())->toBe(0);
});
