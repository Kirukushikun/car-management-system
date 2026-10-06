<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/**
 * Go-live step: create the first IT Admin, who then creates everyone else in Users & Roles.
 */
#[Signature('app:create-admin {email} {name=IT Admin}')]
#[Description('Create an IT Admin account (prompts for the password)')]
class CreateAdmin extends Command
{
    public function handle(): int
    {
        $password = (string) $this->secret('Password (at least 8 characters)');

        $validator = Validator::make(
            ['email' => $this->argument('email'), 'name' => $this->argument('name'), 'password' => $password],
            ['email' => ['required', 'email', 'unique:users,email'], 'name' => ['required', 'string', 'max:255'], 'password' => ['required', 'string', 'min:8']],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        User::create([
            'name' => $this->argument('name'),
            'email' => $this->argument('email'),
            'password' => $password,
            'role' => Role::Admin,
            'is_active' => true,
        ]);

        $this->info("IT Admin {$this->argument('email')} created. Sign in and add the other users in Users & Roles.");

        return self::SUCCESS;
    }
}
