<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Farm;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Testing-mode data: one sample account per role (the people named on the sample CAR forms) and
 * the mockup's ten sample CARs. Every account is flagged is_sample and uses the shared sample
 * password from config/login.php, so it signs in locally without the central Auth API.
 *
 * Refuses to run in production — the third lock that keeps sample accounts out of real systems.
 * Requires ReferenceDataSeeder.
 */
class TestSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('TestSeeder creates sample accounts and must never run in production.');
        }

        $pfc = Farm::where('name', 'PFC')->firstOrFail();

        $stephanie = $this->person('Stephanie Flores', 'stephanie.flores', Role::RequestorApprover);
        $reneliza = $this->person('Reneliza M. Yusi', 'reneliza.yusi', Role::ResponderApprover, $pfc);

        $this->person('Gab Maglalang', 'gab.maglalang', Role::Requestor, approver: $stephanie);
        $this->person('Alvin G. Fabella', 'alvin.fabella', Role::Requestor, approver: $stephanie);
        $this->person('Roi Andre D. Capiz', 'roi.capiz', Role::Responder, $pfc, $reneliza);
        $this->person('QA Monitor', 'qa.monitor', Role::Monitor);
        $this->person('IT Admin', 'it.admin', Role::Admin);

        $this->call(SampleCarSeeder::class);
    }

    private function person(string $name, string $handle, Role $role, ?Farm $farm = null, ?User $approver = null): User
    {
        return User::factory()->sample()->create([
            'name' => $name,
            'email' => "{$handle}@car.test",
            'role' => $role,
            'farm_id' => $farm?->id,
            'approver_id' => $approver?->id,
        ]);
    }
}
