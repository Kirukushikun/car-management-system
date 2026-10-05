<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Hands out CAR references — CAR-{year}-{0001} — numbered per year. The year's sequence row is
 * locked for the duration of the surrounding transaction, so concurrent submissions can never
 * receive the same number.
 */
class CarNumberGenerator
{
    public function next(CarbonInterface $issuedOn): string
    {
        $year = $issuedOn->year;

        return DB::transaction(function () use ($year): string {
            DB::table('car_sequences')->insertOrIgnore(['year' => $year, 'last_number' => 0]);

            $next = DB::table('car_sequences')->where('year', $year)->lockForUpdate()->value('last_number') + 1;

            DB::table('car_sequences')->where('year', $year)->update(['last_number' => $next]);

            return sprintf('CAR-%d-%04d', $year, $next);
        });
    }
}
