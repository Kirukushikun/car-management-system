<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Farm;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * One account per role, using the people named on the sample CAR forms.
 * Requires ReferenceDataSeeder (farms). Every account's password is "password" — for local development only.
 */
class UserSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $pfc = Farm::where('name', 'PFC')->firstOrFail();

        $stephanie = $this->person('Stephanie Flores', 'stephanie.flores', Role::RequestorApprover);
        $reneliza = $this->person('Reneliza M. Yusi', 'reneliza.yusi', Role::ResponderApprover, $pfc);

        $this->person('Gab Maglalang', 'gab.maglalang', Role::Requestor, approver: $stephanie);
        $this->person('Alvin G. Fabella', 'alvin.fabella', Role::Requestor, approver: $stephanie);
        $this->person('Roi Andre D. Capiz', 'roi.capiz', Role::Responder, $pfc, $reneliza);
        $this->person('QA Monitor', 'qa.monitor', Role::Monitor);
        $this->person('IT Admin', 'it.admin', Role::Admin);
    }

    private function person(string $name, string $handle, Role $role, ?Farm $farm = null, ?User $approver = null): User
    {
        return User::factory()->create([
            'name' => $name,
            'email' => "{$handle}@car.test",
            'role' => $role,
            'farm_id' => $farm?->id,
            'approver_id' => $approver?->id,
        ]);
    }
}
