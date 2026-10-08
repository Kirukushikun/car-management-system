<?php

use App\Enums\Role;
use App\Livewire\Auth\Login;
use App\Models\AccessLog;
use App\Models\User;
use App\Services\LoginThrottle;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * Central Auth API responses below follow the shapes documented in the Authentication
 * Implementation Guide. Re-check them against a real response before go-live.
 */
function fakeCentralLogin(int $userId, string $email = 'roi.capiz@bfcgroup.test', int $loginStatus = 200, int $lookupStatus = 200): void
{
    Http::fake([
        'auth.test/api/v1/auth/login' => Http::response(
            $loginStatus === 200 ? ['token' => 'central-token', 'expires_at' => '2026-10-07T18:00:00Z', 'email' => $email] : ['message' => 'Invalid credentials.'],
            $loginStatus,
        ),
        'auth.test/api/v1/users/get-user-id*' => Http::response($lookupStatus === 200 ? ['id' => $userId] : ['message' => 'Not found'], $lookupStatus),
    ]);
}

function submitLogin(string $email, string $password = 'company-password'): Testable
{
    return Livewire::test(Login::class)->set('email', $email)->set('password', $password)->call('login');
}

describe('sample accounts (testing mode)', function () {
    it('lists only active sample accounts and fills the form when one is clicked', function () {
        $sample = User::factory()->sample()->role(Role::Monitor)->create(['name' => 'Quinn Monitor']);
        User::factory()->role(Role::Monitor)->create(['name' => 'Real Person']);
        User::factory()->sample()->inactive()->create(['name' => 'Retired Sample']);

        Livewire::test(Login::class)
            ->assertSee(['Quinn Monitor', 'Sample accounts — development only'])
            ->assertDontSee(['Real Person', 'Retired Sample'])
            ->call('useSample', $sample->email)
            ->assertSet('email', $sample->email)
            ->assertSet('password', 'password');
    });

    it('shows one card per role and tucks the other sample accounts into a collapsed list', function () {
        User::factory()->sample()->role(Role::Responder)->create(['name' => 'Roi Responder']);
        User::factory()->sample()->role(Role::Responder)->create(['name' => 'Brookdale Responder']);
        User::factory()->sample()->role(Role::Monitor)->create(['name' => 'Quinn Monitor']);

        Livewire::test(Login::class)
            ->assertViewHas('roleCards', fn ($cards): bool => $cards->pluck('name')->sort()->values()->all() === ['Quinn Monitor', 'Roi Responder'])
            ->assertViewHas('moreSampleAccounts', fn ($more): bool => $more->pluck('name')->all() === ['Brookdale Responder'])
            ->assertSee('1 more sample account')
            ->call('useSample', User::where('name', 'Brookdale Responder')->value('email'))
            ->assertSet('password', 'password');
    });

    it('signs a sample account in locally without calling the central API', function () {
        $sample = User::factory()->sample()->role(Role::Responder)->create();

        submitLogin($sample->email, 'password')
            ->assertHasNoErrors()
            ->assertRedirect(route('cars.index', ['view' => 'mine']));

        $this->assertAuthenticatedAs($sample);
        Http::assertNothingSent();
    });

    it('sends a sample email with the wrong password to the central API instead', function () {
        $sample = User::factory()->sample()->create();
        fakeCentralLogin($sample->id, $sample->email, loginStatus: 401);

        submitLogin($sample->email, 'wrong')->assertHasErrors('email');

        $this->assertGuest();
        Http::assertSentCount(1);
    });

    it('hints at the seeder when no sample accounts exist outside production', function () {
        Livewire::test(Login::class)->assertSee(['No sample accounts found.', 'php artisan db:seed --class=TestSeeder']);
    });

    it('never offers or accepts sample accounts in production', function () {
        config(['login.sample_accounts' => false]);
        $sample = User::factory()->sample()->create(['name' => 'Hidden Sample']);
        fakeCentralLogin($sample->id, $sample->email, loginStatus: 401);

        Livewire::test(Login::class)
            ->assertDontSee(['Hidden Sample', 'No sample accounts found'])
            ->call('useSample', $sample->email)
            ->assertNotFound();

        submitLogin($sample->email, 'password')->assertHasErrors('email');
        $this->assertGuest();
    });
});

describe('central sign-in', function () {
    it('signs in through the Auth API, keeps the token and lands on the role home page', function () {
        $user = User::factory()->role(Role::Responder)->create(['email' => 'roi.capiz@bfcgroup.test']);
        fakeCentralLogin($user->id);

        submitLogin('roi.capiz@bfcgroup.test')
            ->assertHasNoErrors()
            ->assertRedirect(route('cars.index', ['view' => 'mine']));

        $this->assertAuthenticatedAs($user);
        expect(session('auth_token'))->toBe('central-token')
            ->and(session('token_expires'))->toBe('2026-10-07T18:00:00Z');

        Http::assertSent(fn (Request $request) => $request->url() === 'https://auth.test/api/v1/auth/login'
            && $request->hasHeader('Authorization', 'Bearer test-auth-key')
            && $request['email'] === 'roi.capiz@bfcgroup.test');
        Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://auth.test/api/v1/users/get-user-id')
            && $request->hasHeader('x-api-key', 'test-auth-user-key'));
    });

    it('does not check passwords stored locally', function () {
        $user = User::factory()->create();
        fakeCentralLogin($user->id, $user->email, loginStatus: 401);

        submitLogin($user->email, 'password')->assertHasErrors('email');

        $this->assertGuest();
    });

    it('shows the API message and the attempts left on a wrong password', function () {
        fakeCentralLogin(1, loginStatus: 401);

        submitLogin('someone@bfcgroup.test')
            ->assertHasErrors(['email' => 'Invalid credentials. 2 attempt(s) remaining.']);
    });

    it('refuses someone the IT Admin has not granted access', function () {
        fakeCentralLogin(999);

        submitLogin('roi.capiz@bfcgroup.test')
            ->assertHasErrors(['email' => 'You are not authorized to access this system. 2 attempt(s) remaining.']);

        $this->assertGuest();
    });

    it('refuses a deactivated user', function () {
        $user = User::factory()->inactive()->create();
        fakeCentralLogin($user->id, $user->email);

        submitLogin($user->email)->assertHasErrors('email');

        $this->assertGuest();
    });

    it('reports a failed user lookup without counting it as an attempt', function () {
        fakeCentralLogin(1, lookupStatus: 500);

        submitLogin('roi.capiz@bfcgroup.test')
            ->assertHasErrors(['email' => 'Failed to retrieve user information from the system.']);
    });

    it('does not count an unreachable Auth API as an attempt', function () {
        Http::fake(fn () => throw new ConnectionException('timed out'));

        foreach (range(1, 4) as $attempt) {
            submitLogin('roi.capiz@bfcgroup.test')
                ->assertHasErrors(['email' => 'Authentication service is currently unreachable. Please try again shortly.']);
        }
    });

    it('locks the email for 15 minutes after three failures, without calling the API again', function () {
        fakeCentralLogin(1, loginStatus: 401);

        submitLogin('target@bfcgroup.test');
        submitLogin('target@bfcgroup.test');
        submitLogin('target@bfcgroup.test')
            ->assertHasErrors(['email' => 'Invalid credentials. The account is now locked for 15 minutes.']);

        submitLogin('target@bfcgroup.test')
            ->assertHasErrors(['email' => 'Account temporarily locked. Please try again in 15 minutes.']);

        Http::assertSentCount(3);

        $this->travel(16)->minutes();
        submitLogin('target@bfcgroup.test')->assertHasErrors(['email' => 'Invalid credentials. 2 attempt(s) remaining.']);
    });

    it('clears the failure count after a successful sign-in', function () {
        $user = User::factory()->create(['email' => 'roi.capiz@bfcgroup.test']);
        Http::fake([
            'auth.test/api/v1/auth/login' => Http::sequence()
                ->push(['message' => 'Invalid credentials.'], 401)
                ->push(['token' => 'central-token', 'expires_at' => null, 'email' => $user->email]),
            'auth.test/api/v1/users/get-user-id*' => Http::response(['id' => $user->id]),
        ]);

        submitLogin('roi.capiz@bfcgroup.test')->assertHasErrors('email');
        submitLogin('roi.capiz@bfcgroup.test')->assertHasNoErrors();

        expect(app(LoginThrottle::class)->attempts('roi.capiz@bfcgroup.test'))->toBe(0);
    });

    it('writes every attempt to the access log', function () {
        $user = User::factory()->create(['email' => 'roi.capiz@bfcgroup.test']);
        fakeCentralLogin(999, loginStatus: 401);
        submitLogin('nobody@bfcgroup.test');

        expect(AccessLog::sole())
            ->event->toBe('failed')
            ->success->toBeFalse()
            ->email->toBe('nobody@bfcgroup.test');
    });
});

describe('Cloudflare Turnstile', function () {
    beforeEach(function () {
        config(['login.turnstile' => true, 'services.turnstile.site_key' => 'site-key', 'services.turnstile.secret' => 'secret-key']);
    });

    it('shows the widget only when Turnstile is running', function () {
        Livewire::test(Login::class)->assertSee('challenges.cloudflare.com/turnstile', false);

        config(['login.turnstile' => false]);
        Livewire::test(Login::class)->assertDontSee('challenges.cloudflare.com/turnstile', false);
    });

    it('stops a failed challenge before the Auth API is called', function () {
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => false])]);

        submitLogin('roi.capiz@bfcgroup.test')
            ->assertHasErrors(['turnstileToken' => 'Human verification failed. Please complete the challenge and try again.']);

        Http::assertSentCount(1);
    });

    it('continues to the Auth API after a passed challenge', function () {
        $user = User::factory()->create(['email' => 'roi.capiz@bfcgroup.test']);
        Http::fake([
            'challenges.cloudflare.com/*' => Http::response(['success' => true]),
            'auth.test/api/v1/auth/login' => Http::response(['token' => 't', 'expires_at' => null, 'email' => $user->email]),
            'auth.test/api/v1/users/get-user-id*' => Http::response(['id' => $user->id]),
        ]);

        Livewire::test(Login::class)
            ->set('email', $user->email)->set('password', 'company-password')->set('turnstileToken', 'token-from-widget')
            ->call('login')
            ->assertHasNoErrors();

        $this->assertAuthenticatedAs($user);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'siteverify') && $request['response'] === 'token-from-widget');
    });

    it('is skipped for sample accounts', function () {
        $sample = User::factory()->sample()->create();

        submitLogin($sample->email, 'password')->assertHasNoErrors();

        Http::assertNothingSent();
    });
});

it('renders the sign-in page for guests and sends signed-in users home', function () {
    $this->get(route('login'))->assertOk()->assertSeeLivewire(Login::class)->assertSee('Use your company account.');

    $this->actingAs(User::factory()->role(Role::Admin)->create())->get(route('login'))->assertRedirect(route('admin.users'));
});

it('requires an email and a password', function () {
    Livewire::test(Login::class)
        ->call('login')
        ->assertHasErrors(['email' => 'required', 'password' => 'required']);
});
