<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Go-live step: grant the first IT Admin, who then grants everyone else in Users & Roles.
 * The id must be the person's id in the central directory — they sign in with their company
 * account, so no local password is set.
 */
#[Signature('app:create-admin {id : Central user id from bfcgroup.ph} {email} {name=IT Admin}')]
#[Description('Grant an IT Admin access by central user id')]
class CreateAdmin extends Command
{
    public function handle(): int
    {
        $input = ['id' => $this->argument('id'), 'email' => $this->argument('email'), 'name' => $this->argument('name')];

        $validator = Validator::make($input, [
            'id' => ['required', 'integer', 'min:1', 'unique:users,id'],
            'email' => ['required', 'email', 'unique:users,email'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $admin = new User([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => Str::random(40),
            'role' => Role::Admin,
            'is_active' => true,
        ]);
        $admin->id = (int) $input['id'];
        $admin->save();

        $this->info("IT Admin {$input['email']} (central id {$input['id']}) granted. They sign in with their company account and grant the other users in Users & Roles.");

        return self::SUCCESS;
    }
}
