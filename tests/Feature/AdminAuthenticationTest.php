<?php

use App\Models\AdminUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

uses(RefreshDatabase::class);

function createAdmin(): AdminUser
{
    return AdminUser::query()->create([
        'first_name' => 'Platform',
        'last_name' => 'Admin',
        'mobile' => '9876543210',
        'email' => 'admin@example.com',
        'password' => Hash::make('password'),
        'status' => true,
    ]);
}

test('an admin can log in and receive an admin token', function () {
    createAdmin();

    $this->postJson('/api/admin/login', [
        'email' => 'admin@example.com',
        'password' => 'password',
    ])
        ->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.user.email', 'admin@example.com')
        ->assertJsonStructure(['data' => ['user' => ['id', 'email'], 'token']])
        ->assertJsonMissingPath('data.user.password');
});

test('invalid admin credentials receive an unauthorized response', function () {
    createAdmin();

    $this->postJson('/api/admin/login', [
        'email' => 'admin@example.com',
        'password' => 'incorrect-password',
    ])
        ->assertUnauthorized()
        ->assertJsonPath('message', 'Invalid email or password.');
});

test('an authenticated admin can retrieve their profile', function () {
    $admin = createAdmin();
    $token = $admin->createToken('test-admin-token')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/admin/me')
        ->assertOk()
        ->assertJsonPath('data.email', 'admin@example.com');
});

test('logout revokes the current admin token', function () {
    $admin = createAdmin();
    $token = $admin->createToken('test-admin-token')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/admin/logout')
        ->assertOk()
        ->assertJsonPath('status', true);

    expect(PersonalAccessToken::findToken($token))->toBeNull();
});

test('admin profile requires a valid token', function () {
    $this->getJson('/api/admin/me')->assertUnauthorized();
});

test('a public user token cannot access admin endpoints', function () {
    $user = User::factory()->create();
    $token = $user->createToken('public-user-token')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/admin/me')
        ->assertForbidden()
        ->assertJsonPath('message', 'Unauthorized action.');
});
