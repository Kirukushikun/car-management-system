<?php

namespace App\Livewire\Admin;

use App\Enums\Role;
use App\Models\User;
use App\Services\ScaffoldData;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Users & Roles. Lists the real accounts; adding, editing and deactivating are stubs until Phase 1.
 */
#[Title('Users & Roles')]
class Users extends Component
{
    public ?string $notice = null;

    public function mount(): void
    {
        $this->authorize('administer');
    }

    public function stub(string $what): void
    {
        $this->authorize('administer');

        $this->notice = "{$what} is a stub in the UI scaffold — user management is wired up in Phase 1. Nothing was changed.";
    }

    public function render(): View
    {
        return view('livewire.admin.users', [
            'users' => User::with('approver')->orderBy('role')->orderBy('name')->get(),
            'roles' => Role::cases(),
            'scopes' => [...['Sales'], ...ScaffoldData::farms(), ...['All farms', 'IT']],
        ]);
    }
}
