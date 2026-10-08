<?php

/*
|--------------------------------------------------------------------------
| Login modes — derived, never toggled
|--------------------------------------------------------------------------
|
| Organization standard (Authentication Implementation Guide). Both rules are worked out from
| APP_ENV and from whether the Turnstile keys hold values; there is deliberately no on/off flag.
| Nothing else in the app tests APP_ENV for login purposes — read these instead. After changing
| APP_ENV or the keys, run `php artisan config:clear`.
|
*/

return [

    // Cloudflare Turnstile runs when both keys are filled in — and never on local.
    'turnstile' => env('APP_ENV') !== 'local'
        && filled(env('TURNSTILE_SITE_KEY'))
        && filled(env('TURNSTILE_SECRET_KEY')),

    // Sample accounts (is_sample, created by TestSeeder) appear anywhere that is not production.
    'sample_accounts' => env('APP_ENV') !== 'production',

    // Shared password for every sample account. Not a secret — it is shown on the login page.
    'sample_password' => 'password',

    // Brute-force protection for the central-login path.
    'max_attempts' => 3,
    'lockout_seconds' => 900,

];
