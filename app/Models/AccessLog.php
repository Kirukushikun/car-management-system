<?php

namespace App\Models;

use Database\Factories\AccessLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only record of sign-ins, sign-outs, failed sign-in attempts, attachment downloads and
 * CAR prints.
 */
#[Fillable(['user_id', 'event', 'email', 'success', 'subject', 'ip_address', 'user_agent'])]
class AccessLog extends Model
{
    /** @use HasFactory<AccessLogFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'success' => 'boolean',
        ];
    }

    /**
     * Record a sign-in attempt that did not succeed (wrong password, no access, lockout, outage).
     */
    public static function recordSignInFailure(string $email): self
    {
        $request = request();

        return self::create([
            'user_id' => null,
            'event' => 'failed',
            'email' => $email,
            'success' => false,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    /**
     * Record that the signed-in user opened something sensitive (an attachment, a printed CAR).
     */
    public static function recordAccess(string $event, string $subject): self
    {
        $request = request();

        return self::create([
            'user_id' => $request->user()?->id,
            'event' => $event,
            'email' => $request->user()?->email,
            'subject' => mb_substr($subject, 0, 255),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
