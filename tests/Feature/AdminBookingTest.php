<?php

use App\Models\AdminUser;
use App\Models\Payment;
use App\Models\User;
use App\Models\Wedding;
use App\Models\WeddingBooking;
use App\Models\WeddingDay;
use App\Models\WeddingDayEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $attributes
 */
function createAdminBookingForTest(
    Wedding $wedding,
    array $attributes = [],
    ?string $paymentStatus = Payment::STATUS_PENDING,
): WeddingBooking {
    $createdAt = $attributes['created_at'] ?? '2026-10-01 12:00:00';
    unset($attributes['created_at']);

    $booking = WeddingBooking::create(array_merge([
        'wedding_id' => $wedding->id,
        'user_id' => $wedding->user_id,
        'booking_number' => 'IWI-'.strtoupper(Str::random(10)),
        'status' => WeddingBooking::STATUS_PENDING,
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane@example.com',
        'phone' => '1234567890',
        'number_of_travelers' => 2,
        'price_per_person' => 50,
        'subtotal' => 100,
        'platform_fee' => 10,
        'total_amount' => 100,
        'currency' => 'usd',
    ], $attributes));
    $booking->forceFill(['created_at' => $createdAt])->save();

    if ($paymentStatus !== null) {
        $booking->payment()->create([
            'provider' => 'stripe',
            'payment_intent_id' => 'pi_'.Str::random(16),
            'status' => $paymentStatus,
            'amount' => (int) round((float) $booking->total_amount * 100),
            'currency' => 'usd',
        ]);
    }

    return $booking;
}

function readAdminExportForTest(TestResponse $response): Spreadsheet
{
    $previousValueBinder = Cell::getValueBinder();
    Cell::setValueBinder(new DefaultValueBinder);

    try {
        return IOFactory::load($response->baseResponse->getFile()->getPathname());
    } finally {
        Cell::setValueBinder($previousValueBinder);
    }
}

test('admin booking list and statistics use the same supplied filters', function (
    array $filters,
    array $expectedIndexes,
) {
    Sanctum::actingAs(new AdminUser);
    $wedding = Wedding::factory()->create();
    $bookings = [
        createAdminBookingForTest($wedding, [
            'first_name' => 'Sam', 'last_name' => 'Outside',
            'email' => 'sam@example.com', 'created_at' => '2026-09-30 23:59:59',
        ]),
        createAdminBookingForTest($wedding, [
            'booking_number' => 'IWI-BOUNDARY-START',
            'email' => 'jane.special@example.com',
            'status' => WeddingBooking::STATUS_CONFIRMED,
            'created_at' => '2026-10-01 00:00:00',
        ], Payment::STATUS_SUCCEEDED),
        createAdminBookingForTest($wedding, ['created_at' => '2026-10-01 23:59:59']),
        createAdminBookingForTest($wedding, [
            'first_name' => 'Alex', 'last_name' => 'After',
            'email' => 'alex@example.com', 'status' => WeddingBooking::STATUS_CANCELLED,
            'created_at' => '2026-10-02 00:00:00',
        ], Payment::STATUS_FAILED),
    ];

    $response = $this->getJson('/api/admin/bookings?'.http_build_query($filters));

    $response->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonPath('message', 'Bookings retrieved successfully.')
        ->assertJsonPath('pagination.total', count($expectedIndexes))
        ->assertJsonPath('stats.total_bookings', count($expectedIndexes));
    expect(array_column($response->json('data'), 'id'))->toBe(array_map(
        fn (int $index): int => $bookings[$index]->id,
        $expectedIndexes,
    ));
})->with([
    'no filters' => [[], [3, 2, 1, 0]],
    'booking number' => [['keyword' => 'IWI-BOUNDARY-START'], [1]],
    'first name' => [['keyword' => 'Jane'], [2, 1]],
    'last name' => [['keyword' => 'Doe'], [2, 1]],
    'email' => [['keyword' => 'jane.special@example.com'], [1]],
    'full name' => [['keyword' => 'Jane Doe'], [2, 1]],
    'inclusive start date only' => [['start_date' => '2026-10-01'], [3, 2, 1]],
    'inclusive end date only' => [['end_date' => '2026-10-01'], [2, 1, 0]],
    'inclusive date range' => [['start_date' => '2026-10-01', 'end_date' => '2026-10-01'], [2, 1]],
    'booking status' => [['booking_status' => WeddingBooking::STATUS_PENDING], [2, 0]],
    'existing payment failed booking status' => [['booking_status' => WeddingBooking::STATUS_FAILED], []],
    'payment status' => [['payment_status' => Payment::STATUS_SUCCEEDED], [1]],
    'all five filters' => [[
        'keyword' => 'Jane Doe', 'start_date' => '2026-10-01', 'end_date' => '2026-10-01',
        'booking_status' => WeddingBooking::STATUS_CONFIRMED, 'payment_status' => Payment::STATUS_SUCCEEDED,
    ], [1]],
    'empty filters' => [[
        'keyword' => '', 'start_date' => '', 'end_date' => '',
        'booking_status' => '', 'payment_status' => '',
    ], [3, 2, 1, 0]],
    'whitespace keyword' => [['keyword' => '   '], [3, 2, 1, 0]],
    'SQL-looking keyword remains a search term' => [['keyword' => "' OR 1=1 --"], []],
]);

test('admin booking pagination preserves the default page size and counts all filtered bookings', function () {
    Sanctum::actingAs(new AdminUser);
    $wedding = Wedding::factory()->create();
    $bookings = collect(range(1, 16))->map(
        fn (int $number): WeddingBooking => createAdminBookingForTest($wedding),
    );

    $response = $this->getJson('/api/admin/bookings?page=2');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $bookings->first()->id)
        ->assertJsonPath('pagination.current_page', 2)
        ->assertJsonPath('pagination.per_page', 15)
        ->assertJsonPath('pagination.total', 16)
        ->assertJsonPath('pagination.last_page', 2)
        ->assertJsonPath('stats.total_bookings', 16)
        ->assertJsonPath('stats.pending', 16);
});

test('admin booking limit and per page controls do not reduce statistics', function (
    array $controls,
    string $paginationPath,
    mixed $paginationValue,
) {
    Sanctum::actingAs(new AdminUser);
    $wedding = Wedding::factory()->create();
    createAdminBookingForTest($wedding);
    createAdminBookingForTest($wedding);
    $latestBooking = createAdminBookingForTest($wedding);

    $response = $this->getJson('/api/admin/bookings?'.http_build_query($controls));

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $latestBooking->id)
        ->assertJsonPath($paginationPath, $paginationValue)
        ->assertJsonPath('stats.total_bookings', 3)
        ->assertJsonPath('stats.total_amount', 300);
})->with([
    'legacy limit' => [['limit' => 1], 'pagination', null],
    'custom per page' => [['per_page' => 1], 'pagination.per_page', 1],
]);

test('admin dashboard statistics include revenue only for successful confirmed or completed bookings', function () {
    Sanctum::actingAs(new AdminUser);
    $host = User::factory()->create(['is_host' => true]);
    $wedding = Wedding::factory()->for($host)->create();
    createAdminBookingForTest($wedding, ['status' => WeddingBooking::STATUS_CONFIRMED], Payment::STATUS_SUCCEEDED);
    createAdminBookingForTest($wedding, [
        'status' => WeddingBooking::STATUS_COMPLETED, 'total_amount' => 200, 'platform_fee' => 20,
    ], Payment::STATUS_SUCCEEDED);
    createAdminBookingForTest($wedding, ['status' => WeddingBooking::STATUS_CONFIRMED], Payment::STATUS_PENDING);
    createAdminBookingForTest($wedding, ['status' => WeddingBooking::STATUS_CONFIRMED], Payment::STATUS_FAILED);
    createAdminBookingForTest($wedding, ['status' => WeddingBooking::STATUS_CONFIRMED], null);
    createAdminBookingForTest($wedding, [], Payment::STATUS_SUCCEEDED);
    createAdminBookingForTest($wedding, ['status' => WeddingBooking::STATUS_CANCELLED], Payment::STATUS_SUCCEEDED);

    $response = $this->getJson('/api/admin/wedding-bookings/stats');

    $response->assertOk()
        ->assertJsonPath('data.users_count', 1)
        ->assertJsonPath('data.hosts_count', 1)
        ->assertJsonPath('data.registered_weddings_count', 1)
        ->assertJsonPath('data.wedding_bookings_count', 7)
        ->assertJsonPath('data.total_bookings', 7)
        ->assertJsonPath('data.total_travelers', 14)
        ->assertJsonPath('data.total_amount', 800)
        ->assertJsonPath('data.total_revenue', 300)
        ->assertJsonPath('data.total_platform_fee', 30)
        ->assertJsonPath('data.total_payout_amount', 270)
        ->assertJsonPath('data.pending', 1)
        ->assertJsonPath('data.confirmed', 4)
        ->assertJsonPath('data.cancelled', 1)
        ->assertJsonPath('data.bookings_by_status.completed', 1);
});

test('admin dashboard statistics apply all five filters without limiting global platform counts', function () {
    Sanctum::actingAs(new AdminUser);
    $wedding = Wedding::factory()->create();
    Wedding::factory()->create();
    $filters = [
        'keyword' => 'Jane Doe', 'start_date' => '2026-10-01', 'end_date' => '2026-10-01',
        'booking_status' => WeddingBooking::STATUS_CONFIRMED, 'payment_status' => Payment::STATUS_SUCCEEDED,
        'limit' => 1, 'per_page' => 1, 'page' => 2,
    ];
    createAdminBookingForTest($wedding, ['status' => WeddingBooking::STATUS_CONFIRMED], Payment::STATUS_SUCCEEDED);
    createAdminBookingForTest($wedding, ['status' => WeddingBooking::STATUS_CONFIRMED], Payment::STATUS_SUCCEEDED);
    createAdminBookingForTest($wedding, ['status' => WeddingBooking::STATUS_CONFIRMED], Payment::STATUS_FAILED);
    createAdminBookingForTest($wedding);
    createAdminBookingForTest($wedding, [
        'status' => WeddingBooking::STATUS_CONFIRMED, 'created_at' => '2026-09-30 12:00:00',
    ], Payment::STATUS_SUCCEEDED);
    createAdminBookingForTest($wedding, [
        'status' => WeddingBooking::STATUS_CONFIRMED, 'first_name' => 'Other',
    ], Payment::STATUS_SUCCEEDED);

    $response = $this->getJson('/api/admin/wedding-bookings/stats?'.http_build_query($filters));

    $response->assertOk()
        ->assertJsonPath('data.total_bookings', 2)
        ->assertJsonPath('data.confirmed', 2)
        ->assertJsonPath('data.pending', 0)
        ->assertJsonPath('data.cancelled', 0)
        ->assertJsonPath('data.total_revenue', 200)
        ->assertJsonPath('data.registered_weddings_count', 2)
        ->assertJsonPath('data.users_count', 2);
});

test('admin booking export downloads every matching booking regardless of pagination controls', function () {
    Sanctum::actingAs(new AdminUser);
    $wedding = Wedding::factory()->create();
    $filters = [
        'keyword' => 'Jane Doe', 'start_date' => '2026-10-01', 'end_date' => '2026-10-01',
        'booking_status' => WeddingBooking::STATUS_CONFIRMED, 'payment_status' => Payment::STATUS_SUCCEEDED,
        'limit' => 1, 'per_page' => 1, 'page' => 2,
    ];
    $firstBooking = createAdminBookingForTest($wedding, ['status' => WeddingBooking::STATUS_CONFIRMED], Payment::STATUS_SUCCEEDED);
    $latestBooking = createAdminBookingForTest($wedding, ['status' => WeddingBooking::STATUS_CONFIRMED], Payment::STATUS_SUCCEEDED);
    createAdminBookingForTest($wedding, ['status' => WeddingBooking::STATUS_CONFIRMED], Payment::STATUS_FAILED);
    createAdminBookingForTest($wedding);
    createAdminBookingForTest($wedding, [
        'status' => WeddingBooking::STATUS_CONFIRMED, 'created_at' => '2026-09-30 12:00:00',
    ], Payment::STATUS_SUCCEEDED);
    createAdminBookingForTest($wedding, [
        'status' => WeddingBooking::STATUS_CONFIRMED, 'first_name' => 'Other',
    ], Payment::STATUS_SUCCEEDED);

    $response = $this->get('/api/admin/bookings/export?'.http_build_query($filters));

    $response->assertOk()->assertDownload();
    $spreadsheet = readAdminExportForTest($response);
    $rows = $spreadsheet->getActiveSheet()->toArray();
    expect($rows)->toHaveCount(3);
    expect($rows[0])->toBe([
        'Booking ID', 'Guest Name', 'Wedding', 'Wedding Dates', 'Guests', 'Amount',
        'Payment Status', 'Booking Status', 'Booked At',
    ]);
    expect(array_column(array_slice($rows, 1), 0))->toBe([
        $latestBooking->booking_number, $firstBooking->booking_number,
    ]);
    expect($rows[1][1])->toBe('Jane Doe');
    expect($rows[1][5])->toEqual(100);
    $spreadsheet->disconnectWorksheets();
});

test('admin booking export treats guest names as literal text and amounts as numbers', function () {
    Sanctum::actingAs(new AdminUser);
    $wedding = Wedding::factory()->create();
    createAdminBookingForTest($wedding, ['first_name' => '=1+1', 'last_name' => '']);

    $response = $this->get('/api/admin/bookings/export');

    $response->assertOk()->assertDownload();
    $spreadsheet = readAdminExportForTest($response);
    $sheet = $spreadsheet->getActiveSheet();
    expect($sheet->getCell('B2')->getValue())->toBe('=1+1');
    expect($sheet->getCell('B2')->getDataType())->toBe(DataType::TYPE_STRING);
    expect($sheet->getCell('F2')->getDataType())->toBe(DataType::TYPE_NUMERIC);
    $spreadsheet->disconnectWorksheets();
});

test('admin wedding export continues to download the existing wedding spreadsheet', function () {
    Sanctum::actingAs(new AdminUser);
    $wedding = Wedding::factory()->create();

    $response = $this->get('/api/admin/weddings/export');

    $response->assertOk()->assertDownload();
    $spreadsheet = readAdminExportForTest($response);
    $sheet = $spreadsheet->getActiveSheet();
    expect($sheet->getHighestRow())->toBe(2);
    expect($sheet->getCell('A1')->getValue())->toBe('ID');
    expect($sheet->getCell('A2')->getValue())->toBe($wedding->id);
    $spreadsheet->disconnectWorksheets();
});

test('admin booking list includes selected day events without lazy loading relationships', function () {
    Sanctum::actingAs(new AdminUser);
    $wedding = Wedding::factory()->create();
    foreach (range(1, 2) as $number) {
        $booking = createAdminBookingForTest($wedding);
        $day = WeddingDay::factory()->for($wedding)
            ->afterMaking(function (WeddingDay $day): void {
                unset($day->day_number);
            })->create(['wedding_day_date' => '2026-10-10']);
        WeddingDayEvent::factory()->for($day, 'weddingDay')
            ->afterMaking(function (WeddingDayEvent $event): void {
                $event->setRawAttributes(collect($event->getAttributes())->only([
                    'wedding_day_id', 'title', 'description', 'is_music_or_dancing',
                    'dress_code', 'sort_order',
                ])->all());
            })->create();
        $booking->days()->create(['wedding_day_id' => $day->id]);
    }
    $previouslyPreventedLazyLoading = Model::preventsLazyLoading();
    Model::preventLazyLoading();

    try {
        $response = $this->getJson('/api/admin/bookings');

        $response->assertOk()
            ->assertJsonPath('data.0.booking_days_events_count', '1 Day 1 event')
            ->assertJsonCount(1, 'data.0.wedding_days.0.booking_day_events');
    } finally {
        Model::preventLazyLoading($previouslyPreventedLazyLoading);
    }
});

test('admin booking list returns 422 for invalid filters', function (array $filters, string $field) {
    Sanctum::actingAs(new AdminUser);

    $response = $this->getJson('/api/admin/bookings?'.http_build_query($filters));

    $response->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'invalid booking status' => [['booking_status' => 'paid'], 'booking_status'],
    'invalid payment status' => [['payment_status' => 'paid'], 'payment_status'],
    'invalid start date' => [['start_date' => 'not-a-date'], 'start_date'],
    'invalid end date' => [['end_date' => 'not-a-date'], 'end_date'],
    'reversed date range' => [['start_date' => '2026-10-02', 'end_date' => '2026-10-01'], 'end_date'],
    'oversized keyword' => [['keyword' => str_repeat('a', 256)], 'keyword'],
    'invalid per page' => [['per_page' => 0], 'per_page'],
    'invalid limit' => [['limit' => 0], 'limit'],
]);

test('admin booking statistics and export return 422 for invalid status filters', function (string $endpoint) {
    Sanctum::actingAs(new AdminUser);

    $response = $this->getJson($endpoint.'?booking_status=unknown');

    $response->assertUnprocessable()->assertJsonValidationErrors('booking_status');
})->with([
    'statistics' => '/api/admin/wedding-bookings/stats',
    'export' => '/api/admin/bookings/export',
]);

test('admin booking endpoints return 403 without authentication', function (string $endpoint) {
    $this->getJson($endpoint)->assertForbidden();
})->with([
    'list' => '/api/admin/bookings',
    'statistics' => '/api/admin/wedding-bookings/stats',
    'export' => '/api/admin/bookings/export',
]);

test('admin booking endpoints return 403 for a regular user', function (string $endpoint) {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson($endpoint)->assertForbidden();
})->with([
    'list' => '/api/admin/bookings',
    'statistics' => '/api/admin/wedding-bookings/stats',
    'export' => '/api/admin/bookings/export',
]);
