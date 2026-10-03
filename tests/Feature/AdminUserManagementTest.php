<?php

use App\Models\AdminUser;
use App\Models\User;
use App\Models\Wedding;
use App\Models\WeddingBooking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;

uses(RefreshDatabase::class);

test('admin status updates change only status', function (mixed $status, bool $expected) {
    Sanctum::actingAs(new AdminUser);
    $user = User::factory()->create(['status' => ! $expected, 'name' => 'Original', 'is_host' => true]);
    $password = $user->password;

    $this->putJson('/api/admin/users-status/'.$user->id, [
        'status' => $status, 'name' => 'Changed', 'is_host' => false, 'password' => 'changed',
    ])->assertOk()->assertJsonPath('data.status', $expected)
        ->assertJsonPath('message', 'User status updated successfully.');

    expect($user->fresh()->status)->toBe($expected);
    expect($user->fresh()->name)->toBe('Original');
    expect($user->fresh()->is_host)->toBeTrue();
    expect($user->fresh()->password)->toBe($password);
})->with([
    [true, true], [false, false], [1, true], [0, false],
    ['1', true], ['0', false], ['true', true], ['false', false],
]);

test('invalid status updates are rejected without changing the user', function (array $payload) {
    Sanctum::actingAs(new AdminUser);
    $user = User::factory()->create(['status' => true]);

    $this->putJson('/api/admin/users-status/'.$user->id, $payload)
        ->assertUnprocessable()->assertJsonValidationErrors('status');

    expect($user->fresh()->status)->toBeTrue();
})->with([[[]], [['status' => null]], [['status' => '']], [['status' => 2]], [['status' => 'active']]]);

test('status updates require an admin and preserve the standard missing user response', function () {
    $user = User::factory()->create(['status' => true]);

    $this->putJson('/api/admin/users-status/'.$user->id, ['status' => false])->assertForbidden();
    Sanctum::actingAs($user);
    $this->putJson('/api/admin/users-status/'.$user->id, ['status' => false])->assertForbidden();
    expect($user->fresh()->status)->toBeTrue();

    Sanctum::actingAs(new AdminUser);
    $this->putJson('/api/admin/users-status/999', ['status' => false])
        ->assertNotFound()->assertJsonPath('message', 'Record not found');
});

test('list and export share user filters including false and zero', function (array $filters, array $indexes) {
    Sanctum::actingAs(new AdminUser);
    $users = collect([
        ['name' => 'John Before', 'email' => 'before@example.com', 'phone' => '1000', 'is_host' => false, 'status' => false, 'created_at' => '2026-09-30 23:59:59'],
        ['name' => 'John Start', 'email' => 'start@example.com', 'phone' => '2000', 'is_host' => true, 'status' => true, 'created_at' => '2026-10-01 00:00:00'],
        ['name' => 'Jane End', 'email' => 'john.end@example.com', 'phone' => '3000', 'is_host' => false, 'status' => false, 'created_at' => '2026-10-01 23:59:59'],
        ['name' => 'Other After', 'email' => 'after@example.com', 'phone' => '4000', 'is_host' => true, 'status' => false, 'created_at' => '2026-10-02 00:00:00'],
    ])->map(fn (array $attributes): User => User::factory()->create($attributes));
    $expectedIds = array_map(fn (int $index): int => $users[$index]->id, $indexes);
    $parameters = http_build_query([...$filters, 'per_page' => 1, 'page' => 1]);

    $response = $this->getJson('/api/admin/users?'.$parameters);

    $response->assertOk()->assertJsonPath('pagination.total', count($indexes))
        ->assertJsonPath('pagination.per_page', 1);
    expect(array_column($response->json('data'), 'id'))->toBe(array_slice($expectedIds, 0, 1));
    foreach ($response->json('data') as $record) {
        expect($record)->not->toHaveKeys(['password', 'remember_token', 'stripe_id', 'pm_last_four']);
        expect($record['user_type'])->toBe($record['is_host']);
    }

    $export = $this->getJson('/api/admin/users/export?'.$parameters);
    $export->assertOk()->assertDownload();
    $sheet = IOFactory::load($export->baseResponse->getFile()->getPathname())->getActiveSheet();
    $rows = $sheet->toArray();
    expect(array_shift($rows))->toBe(['ID', 'Name', 'Email', 'Phone', 'User Type', 'Status', 'Created At', 'Updated At']);
    expect(array_map(fn (array $row): int => (int) $row[0], $rows))->toBe($expectedIds);
})->with([
    'no filters' => [[], [3, 2, 1, 0]],
    'empty filters' => [['keyword' => '', 'start_date' => '', 'end_date' => '', 'user_type' => '', 'status' => ''], [3, 2, 1, 0]],
    'host true' => [['user_type' => true], [3, 1]],
    'host false' => [['user_type' => false], [2, 0]],
    'host true string' => [['user_type' => 'true'], [3, 1]],
    'host false string' => [['user_type' => 'false'], [2, 0]],
    'host one' => [['user_type' => 1], [3, 1]],
    'host zero' => [['user_type' => 0], [2, 0]],
    'status one' => [['status' => 1], [1]],
    'status zero' => [['status' => 0], [3, 2, 0]],
    'status true string' => [['status' => 'true'], [1]],
    'status false string' => [['status' => 'false'], [3, 2, 0]],
    'false and zero' => [['user_type' => false, 'status' => 0], [2, 0]],
    'name partial case insensitive' => [['keyword' => 'JOHN'], [2, 1, 0]],
    'email' => [['keyword' => 'john.end'], [2]],
    'phone' => [['keyword' => '300'], [2]],
    'start only' => [['start_date' => '2026-10-01'], [3, 2, 1]],
    'end only' => [['end_date' => '2026-10-01'], [2, 1, 0]],
    'all filters grouped' => [['keyword' => 'john', 'start_date' => '2026-10-01', 'end_date' => '2026-10-01', 'user_type' => 'false', 'status' => 0], [2]],
    'SQL-looking keyword' => [['keyword' => "' OR 1=1 --"], []],
    'unrecognized filters ignored' => [['city' => 'missing', 'wedding_id' => 999], [3, 2, 1, 0]],
]);

test('users are paginated with the existing default size', function () {
    Sanctum::actingAs(new AdminUser);
    $users = User::factory()->count(16)->create();

    $this->getJson('/api/admin/users?page=2')->assertOk()
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $users->first()->id)
        ->assertJsonPath('pagination.per_page', 15)->assertJsonPath('pagination.total', 16);
});

test('show returns the selected user without authentication or billing secrets', function () {
    Sanctum::actingAs(new AdminUser);
    $user = User::factory()->create(['is_host' => true, 'stripe_id' => 'cus_secret']);

    $response = $this->getJson('/api/admin/users/'.$user->id);

    $response->assertOk()->assertJsonPath('data.id', $user->id)->assertJsonPath('data.user_type', true);
    expect($response->json('data'))->not->toHaveKeys(['password', 'remember_token', 'stripe_id']);
});

test('missing users return the standard 404 response', function (string $method) {
    Sanctum::actingAs(new AdminUser);

    $this->{$method}('/api/admin/users/999')->assertNotFound()->assertJsonPath('status', false)
        ->assertJsonPath('message', 'Record not found');
})->with(['getJson', 'deleteJson']);

test('all user management endpoints require admin authentication', function (string $method, string $path) {
    $user = User::factory()->create();
    $url = '/api/admin/users'.$path;

    $this->{$method}($url)->assertForbidden();
    Sanctum::actingAs($user);
    $this->{$method}($url)->assertForbidden()->assertJsonPath('status', false);
})->with([
    ['getJson', ''], ['getJson', '/export'], ['getJson', '/1'], ['deleteJson', '/1'],
]);

test('invalid user filters return validation errors for list and export', function (array $filters, string $field) {
    Sanctum::actingAs(new AdminUser);
    foreach (['', '/export'] as $suffix) {
        $this->getJson('/api/admin/users'.$suffix.'?'.http_build_query($filters))
            ->assertUnprocessable()->assertJsonValidationErrors($field);
    }
})->with([
    [['keyword' => ['invalid']], 'keyword'],
    [['keyword' => str_repeat('a', 256)], 'keyword'],
    [['start_date' => 'invalid'], 'start_date'],
    [['end_date' => 'invalid'], 'end_date'],
    [['start_date' => '2026-10-02', 'end_date' => '2026-10-01'], 'end_date'],
    [['user_type' => 'host'], 'user_type'],
    [['status' => 2], 'status'],
]);

test('invalid pagination is rejected', function (array $parameters, string $field) {
    Sanctum::actingAs(new AdminUser);

    $this->getJson('/api/admin/users?'.http_build_query($parameters))
        ->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    [['per_page' => 0], 'per_page'], [['per_page' => 101], 'per_page'],
    [['per_page' => 'abc'], 'per_page'], [['page' => 0], 'page'],
]);

test('deletion removes the user and authentication records atomically', function () {
    Sanctum::actingAs(new AdminUser);
    $user = User::factory()->create();
    $user->createToken('test');
    $connection = $user->getConnection();
    $connection->table('sessions')->insert(['id' => 'user-session', 'user_id' => $user->id, 'payload' => '', 'last_activity' => 1]);
    $connection->table('password_reset_tokens')->insert(['email' => $user->email, 'token' => 'reset']);
    $user->socialAccounts()->create(['provider' => 'google', 'provider_id' => 'social-user']);

    $this->deleteJson('/api/admin/users/'.$user->id)->assertOk()->assertJsonPath('status', true);

    $this->assertModelMissing($user);
    $this->assertDatabaseCount('personal_access_tokens', 0);
    $this->assertDatabaseCount('social_accounts', 0);
    $this->assertDatabaseCount('sessions', 0);
    $this->assertDatabaseCount('password_reset_tokens', 0);
});

test('deletion refuses users referenced by weddings or subscriptions', function (string $reference) {
    Sanctum::actingAs(new AdminUser);
    $user = User::factory()->create();
    $token = $user->createToken('test')->accessToken;
    if ($reference === 'wedding') {
        $record = Wedding::factory()->create(['user_id' => $user->id]);
    } else {
        $record = $user->subscriptions()->create(['type' => 'default', 'stripe_id' => 'sub_test', 'stripe_status' => 'active']);
    }

    $this->deleteJson('/api/admin/users/'.$user->id)->assertStatus(400)->assertJsonPath('status', false);

    $this->assertModelExists($user);
    $this->assertModelExists($record);
    $this->assertModelExists($token);
})->with(['wedding', 'subscription']);

test('export writes untrusted names and phone numbers as literal strings', function () {
    Sanctum::actingAs(new AdminUser);
    User::factory()->create(['name' => '=1+1', 'phone' => '+0123456']);

    $response = $this->getJson('/api/admin/users/export');

    $response->assertOk();
    $sheet = IOFactory::load($response->baseResponse->getFile()->getPathname())->getActiveSheet();
    expect($sheet->getCell('B2')->getDataType())->toBe(DataType::TYPE_STRING);
    expect($sheet->getCell('B2')->getValue())->toBe('=1+1');
    expect($sheet->getCell('D2')->getValue())->toBe('+0123456');
});

test('deleting a guest preserves their booking and payment history', function () {
    Sanctum::actingAs(new AdminUser);
    $guest = User::factory()->create();
    $wedding = Wedding::factory()->create();
    $booking = WeddingBooking::create([
        'wedding_id' => $wedding->id, 'user_id' => $guest->id,
        'booking_number' => 'IWI-DELETE-GUEST', 'status' => 'confirmed',
        'first_name' => 'Guest', 'last_name' => 'Test', 'email' => $guest->email,
        'phone' => $guest->phone, 'number_of_travelers' => 1,
        'price_per_person' => 50, 'subtotal' => 50, 'platform_fee' => 5,
        'total_amount' => 50, 'currency' => 'usd',
    ]);
    $payment = $booking->payment()->create([
        'provider' => 'stripe', 'payment_intent_id' => 'pi_preserved',
        'status' => 'succeeded', 'amount' => 5000, 'currency' => 'usd',
    ]);

    $this->deleteJson('/api/admin/users/'.$guest->id)->assertOk()->assertJsonPath('status', true);

    $this->assertModelMissing($guest);
    $this->assertModelExists($wedding);
    $this->assertModelExists($booking);
    $this->assertModelExists($payment);
    expect($booking->fresh()->user_id)->toBeNull();
});
