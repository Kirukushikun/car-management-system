<?php

namespace App\Listeners;

use App\Models\AccessLog;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;

/**
 * Writes an access-log row for every sign-in and sign-out. Failed sign-ins are recorded by the
 * login screen itself (AccessLog::recordSignInFailure), since they never reach Laravel's guard.
 * Registered through event discovery — one handler method per event.
 */
class RecordAccessLog
{
    public function __construct(public Request $request) {}

    public function handleLogin(Login $event): void
    {
        $this->record('login', $event->user, true);
    }

    public function handleLogout(Logout $event): void
    {
        $this->record('logout', $event->user, null);
    }

    private function record(string $event, ?Authenticatable $user, ?bool $success): void
    {
        AccessLog::create([
            'user_id' => $user?->getAuthIdentifier(),
            'event' => $event,
            'email' => $user->email ?? null,
            'success' => $success,
            'ip_address' => $this->request->ip(),
            'user_agent' => $this->request->userAgent(),
        ]);
    }
}
