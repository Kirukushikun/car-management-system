<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Farm;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Testing-mode accounts: one per role (the people named on the sample CAR forms), plus a Responder
 * and Responder Approver for every other farm so a CAR can be walked through for any farm. No CARs
 * — the system starts empty; `db:seed --class=SampleCarSeeder` adds the mockup's ten if wanted.
 * Every account is flagged is_sample and uses the shared sample password from config/login.php,
 * so it signs in without the central Auth API.
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

        $requestorApprover = $this->person('Sales Requestor Approver', 'sales.requestor-approver', Role::RequestorApprover);
        $responderApprover = $this->person('PFC Responder Approver', 'pfc.responder-approver', Role::ResponderApprover, $pfc);

        $this->person('Sales Requestor', 'sales.requestor', Role::Requestor, approver: $requestorApprover);
        $this->person('Sales Requestor 2', 'sales.requestor-2', Role::Requestor, approver: $requestorApprover);
        $this->person('PFC Responder', 'pfc.responder', Role::Responder, $pfc, $responderApprover);
        $this->person('QA Monitor', 'qa.monitor', Role::Monitor);
        $this->person('IT Admin', 'it.admin', Role::Admin);

        Farm::whereKeyNot($pfc->id)->orderBy('name')->each(function (Farm $farm): void {
            $handle = str($farm->name)->lower()->replace('/', '-');
            $name = ucwords(strtolower($farm->name));

            $approver = $this->person("{$name} Responder Approver", "{$handle}.responder-approver", Role::ResponderApprover, $farm);
            $this->person("{$name} Responder", "{$handle}.responder", Role::Responder, $farm, $approver);
        });
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
