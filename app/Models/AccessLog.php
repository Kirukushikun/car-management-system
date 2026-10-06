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
#[Fillable(['user_id', 'event', 'email', 'subject', 'ip_address', 'user_agent'])]
class AccessLog extends Model
{
    /** @use HasFactory<AccessLogFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

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
