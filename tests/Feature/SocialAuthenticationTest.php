<?php

use App\Models\SocialLoginCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialUser;

uses(RefreshDatabase::class);

test('inactive social accounts redirect to unauthorized without authentication records', function (string $provider, bool $linked) {
    config(['app.frontend_url' => 'https://frontend.example.test/']);
    $user = User::factory()->create(['status' => false]);
    if ($linked) {
        $user->socialAccounts()->create(['provider' => $provider, 'provider_id' => 'provider-user']);
    }
    Socialite::fake($provider, SocialUser::fake([
        'id' => 'provider-user', 'email' => $user->email, 'name' => 'Social User',
    ]));

    $this->get('/api/auth/social/'.$provider.'/callback')
        ->assertRedirect('https://frontend.example.test/unauthorized');

    $this->assertDatabaseCount('users', 1);
    $this->assertDatabaseCount('social_accounts', $linked ? 1 : 0);
    $this->assertDatabaseCount('personal_access_tokens', 0);
    $this->assertDatabaseCount('social_login_codes', 0);
    expect($user->fresh()->status)->toBeFalse();
})->with([
    'google email match' => ['google', false],
    'google linked' => ['google', true],
    'facebook email match' => ['facebook', false],
    'facebook linked' => ['facebook', true],
]);

test('active and newly registered social users retain the callback and exchange flow', function (string $account) {
    config(['app.frontend_url' => 'https://frontend.example.test']);
    $email = 'social@example.test';
    $user = null;
    if ($account !== 'new') {
        $user = User::factory()->create(['email' => $email, 'status' => true]);
        if ($account === 'linked') {
            $user->socialAccounts()->create(['provider' => 'google', 'provider_id' => 'provider-user']);
        }
    }
    Socialite::fake('google', SocialUser::fake([
        'id' => 'provider-user', 'email' => $email, 'name' => 'Social User',
    ]));

    $callback = $this->get('/api/auth/social/google/callback');

    $callback->assertRedirect();
    $url = $callback->headers->get('Location');
    expect($url)->toStartWith('https://frontend.example.test/auth/social/callback?code=');
    parse_str(parse_url($url, PHP_URL_QUERY), $parameters);
    $this->assertDatabaseCount('users', 1);
    $this->assertDatabaseCount('social_accounts', 1);
    $expectedUser = $user ?? User::where('email', $email)->firstOrFail();

    $response = $this->postJson('/api/auth/social/exchange', ['code' => $parameters['code']]);

    $response->assertOk()->assertJsonPath('message', 'Social login successful.')
        ->assertJsonPath('user.id', $expectedUser->id)->assertJsonPath('token_type', 'Bearer');
    expect(PersonalAccessToken::findToken($response->json('token'))->tokenable_id)->toBe($expectedUser->id);
    $this->postJson('/api/auth/social/exchange', ['code' => $parameters['code']])->assertUnauthorized();
})->with(['new', 'existing email', 'linked']);

test('code exchange rejects an account deactivated after its callback', function () {
    $this->freezeTime();
    $user = User::factory()->create(['status' => true]);
    $rawCode = str_repeat('a', 64);
    $loginCode = SocialLoginCode::create([
        'user_id' => $user->id, 'code_hash' => hash('sha256', $rawCode),
        'expires_at' => now()->addMinute(),
    ]);
    $user->update(['status' => false]);

    $this->postJson('/api/auth/social/exchange', ['code' => $rawCode])
        ->assertForbidden()->assertJsonPath('status', false)
        ->assertJsonPath('message', 'Unauthorized action.');

    $this->assertDatabaseCount('personal_access_tokens', 0);
    expect($loginCode->fresh()->used_at)->not->toBeNull();
});

test('invalid and expired social codes retain the unauthorized response', function (bool $expired) {
    $this->freezeTime();
    $rawCode = str_repeat('b', 64);
    if ($expired) {
        $user = User::factory()->create();
        SocialLoginCode::create([
            'user_id' => $user->id, 'code_hash' => hash('sha256', $rawCode),
            'expires_at' => now()->subMinute(),
        ]);
    }

    $this->postJson('/api/auth/social/exchange', ['code' => $rawCode])
        ->assertUnauthorized()->assertJsonPath('message', 'Invalid or expired login code.');

    $this->assertDatabaseCount('personal_access_tokens', 0);
})->with(['invalid' => false, 'expired' => true]);
