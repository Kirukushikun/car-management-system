<?php

use App\Enums\Role;
use App\Models\Car;
use App\Models\Farm;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\SampleCarSeeder;
use Database\Seeders\TestSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('local'));

it('creates sample accounts flagged and signed with the shared sample password', function () {
    $this->seed([ReferenceDataSeeder::class, TestSeeder::class]);

    $users = User::all();

    expect($users)->not->toBeEmpty()
        ->and($users->every(fn (User $user): bool => $user->is_sample))->toBeTrue()
        ->and(Hash::check(config('login.sample_password'), User::where('email', 'gab.maglalang@car.test')->sole()->password))->toBeTrue();
});

it('starts with no CARs', function () {
    $this->seed([ReferenceDataSeeder::class, TestSeeder::class]);

    expect(Car::count())->toBe(0);
});

it('gives every farm a Responder and a Responder Approver, the Responder reporting to that approver', function () {
    $this->seed([ReferenceDataSeeder::class, TestSeeder::class]);

    Farm::all()->each(function (Farm $farm): void {
        $approver = User::where('farm_id', $farm->id)->where('role', Role::ResponderApprover)->sole();
        $responder = User::where('farm_id', $farm->id)->where('role', Role::Responder)->sole();

        expect($responder->approver_id)->toBe($approver->id);
    });

    expect(User::count())->toBe(12);
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

    expect(fn () => runSeeder(TestSeeder::class))->toThrow(RuntimeException::class, 'must never run in production')
        ->and(fn () => runSeeder(SampleCarSeeder::class))->toThrow(RuntimeException::class, 'must never run in production');
    expect(User::count())->toBe(0);
});

it('seeds only reference data in production', function () {
    app()->detectEnvironment(fn (): string => 'production');

    runSeeder(DatabaseSeeder::class);

    expect(User::count())->toBe(0)
        ->and(Car::count())->toBe(0);
});
