<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * TEMPORARY — delete before go-live (Authentication Implementation Guide, Part 2).
 *
 * Shows the user-list API's raw response and whether the first record's id decrypts with this
 * system's APP_KEY, so the real response can be checked before relying on it. IT Admin only, and
 * a 404 in production.
 */
class UserDirectoryDebugController extends Controller
{
    public function __invoke(): JsonResponse
    {
        abort_if(app()->isProduction(), 404);

        try {
            $response = Http::withHeaders(['x-api-key' => (string) config('services.user_api.key')])
                ->acceptJson()
                ->withOptions(['verify' => storage_path('cacert.pem')])
                ->timeout(10)
                ->connectTimeout(5)
                ->post((string) config('services.user_api.endpoint'));
        } catch (Throwable $e) {
            return response()->json(['error' => 'Request failed: '.$e->getMessage()], 502, [], JSON_PRETTY_PRINT);
        }

        $body = $response->json() ?? $response->body();
        $first = is_array($body) ? (data_get($body, 'data.0') ?? ($body[0] ?? null)) : null;

        try {
            $decryptCheck = ['id_decrypts_to' => Crypt::decryptString((string) ($first['id'] ?? ''))];
        } catch (DecryptException) {
            $decryptCheck = 'FAILED — APP_KEY mismatch with the central system';
        }

        return response()->json([
            'status' => $response->status(),
            'shape' => is_array($body) ? (array_key_exists('data', $body) ? 'object with data' : (array_is_list($body) ? 'bare array' : 'object')) : 'not JSON',
            'record_count' => is_array($body) ? count($body['data'] ?? $body) : null,
            'first_record_fields' => is_array($first) ? array_keys($first) : null,
            'first_record_decrypt_check' => $decryptCheck,
            'raw' => $body,
        ], 200, [], JSON_PRETTY_PRINT);
    }
}
