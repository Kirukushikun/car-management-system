<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * One account per role, using the people named on the sample CAR forms.
 * Every account's password is "password" — for local development only.
 */
class UserSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $stephanie = $this->person('Stephanie Flores', 'stephanie.flores', Role::RequestorApprover, 'Sales');
        $reneliza = $this->person('Reneliza M. Yusi', 'reneliza.yusi', Role::ResponderApprover, 'PFC');

        $this->person('Gab Maglalang', 'gab.maglalang', Role::Requestor, 'Sales', $stephanie);
        $this->person('Alvin G. Fabella', 'alvin.fabella', Role::Requestor, 'Sales', $stephanie);
        $this->person('Roi Andre D. Capiz', 'roi.capiz', Role::Responder, 'PFC', $reneliza);
        $this->person('QA Monitor', 'qa.monitor', Role::Monitor, 'All farms');
        $this->person('IT Admin', 'it.admin', Role::Admin, 'IT');
    }

    private function person(string $name, string $handle, Role $role, string $scope, ?User $approver = null): User
    {
        return User::factory()->create([
            'name' => $name,
            'email' => "{$handle}@car.test",
            'role' => $role,
            'scope' => $scope,
            'approver_id' => $approver?->id,
        ]);
    }
}
