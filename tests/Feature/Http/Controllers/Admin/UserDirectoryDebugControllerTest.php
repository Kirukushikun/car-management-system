<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.user_api.endpoint' => 'https://auth.test/api/v1/users']);
    $this->admin = User::factory()->role(Role::Admin)->create();
});

it('shows the raw directory response and whether the first id decrypts', function () {
    Http::fake(['auth.test/api/v1/users' => Http::response([
        ['id' => Crypt::encryptString('42'), 'first_name' => 'Maria', 'last_name' => 'Santos', 'email' => 'm@example.org'],
    ])]);

    $this->actingAs($this->admin)
        ->get(route('admin.debug.user-api'))
        ->assertOk()
        ->assertJson([
            'status' => 200,
            'shape' => 'bare array',
            'record_count' => 1,
            'first_record_fields' => ['id', 'first_name', 'last_name', 'email'],
            'first_record_decrypt_check' => ['id_decrypts_to' => '42'],
        ]);
});

it('flags an APP_KEY mismatch', function () {
    Http::fake(['auth.test/api/v1/users' => Http::response([['id' => 'not-ours', 'email' => 'x@example.org']])]);

    $this->actingAs($this->admin)
        ->get(route('admin.debug.user-api'))
        ->assertJson(['first_record_decrypt_check' => 'FAILED — APP_KEY mismatch with the central system']);
});

it('does not exist in production', function () {
    app()->detectEnvironment(fn (): string => 'production');

    $this->actingAs($this->admin)->get(route('admin.debug.user-api'))->assertNotFound();
});

it('is for the IT Admin only', function () {
    $this->actingAs(User::factory()->role(Role::Monitor)->create())
        ->get(route('admin.debug.user-api'))
        ->assertForbidden();
});
