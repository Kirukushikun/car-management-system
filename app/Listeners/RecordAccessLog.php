<?php

namespace App\Listeners;

use App\Models\AccessLog;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;

/**
 * Writes an access-log row for every sign-in, sign-out and failed sign-in.
 * Registered through event discovery — one handler method per event.
 */
class RecordAccessLog
{
    public function __construct(public Request $request) {}

    public function handleLogin(Login $event): void
    {
        $this->record('login', $event->user, $event->user->email ?? null);
    }

    public function handleLogout(Logout $event): void
    {
        $this->record('logout', $event->user, $event->user->email ?? null);
    }

    public function handleFailed(Failed $event): void
    {
        $this->record('failed', $event->user, $event->credentials['email'] ?? null);
    }

    private function record(string $event, ?Authenticatable $user, ?string $email): void
    {
        AccessLog::create([
            'user_id' => $user?->getAuthIdentifier(),
            'event' => $event,
            'email' => $email,
            'ip_address' => $this->request->ip(),
            'user_agent' => $this->request->userAgent(),
        ]);
    }
}
