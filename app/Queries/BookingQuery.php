<?php

namespace App\Queries;

use App\Models\Payment;
use App\Models\WeddingBooking;
use Illuminate\Database\Eloquent\Builder;

class BookingQuery
{
    /**
     * @param  array{keyword?: ?string, start_date?: ?string, end_date?: ?string, booking_status?: ?string, payment_status?: ?string}  $filters
     * @return Builder<WeddingBooking>
     */
    public function build(array $filters): Builder
    {
        $query = WeddingBooking::query();
        $keyword = trim((string) ($filters['keyword'] ?? ''));

        if ($keyword !== '') {
            $like = "%{$keyword}%";
            $fullNameExpression = $query->getConnection()->getDriverName() === 'sqlite'
                ? "first_name || ' ' || last_name"
                : "CONCAT(first_name, ' ', last_name)";

            $query->where(function (Builder $bookingQuery) use ($like, $fullNameExpression): void {
                $bookingQuery->where('booking_number', 'like', $like)
                    ->orWhere('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhereRaw("{$fullNameExpression} LIKE ?", [$like]);
            });
        }

        if (filled($filters['start_date'] ?? null)) {
            $query->whereDate('created_at', '>=', $filters['start_date']);
        }

        if (filled($filters['end_date'] ?? null)) {
            $query->whereDate('created_at', '<=', $filters['end_date']);
        }

        if (filled($filters['booking_status'] ?? null)) {
            $query->where('status', $filters['booking_status']);
        }

        if (filled($filters['payment_status'] ?? null)) {
            $query->whereHas('payment', function (Builder $paymentQuery) use ($filters): void {
                $paymentQuery->where('status', $filters['payment_status']);
            });
        }

        return $query;
    }

    /**
     * @param  array{keyword?: ?string, start_date?: ?string, end_date?: ?string, booking_status?: ?string, payment_status?: ?string}  $filters
     * @return Builder<WeddingBooking>
     */
    public function forList(array $filters): Builder
    {
        $query = $this->build($filters);

        $query->with([
            'wedding.images',
            'wedding.creators',
            'wedding.thumbnail',
            'wedding.days.events',
            'days.weddingDay.events',
            'payment',
        ])
            ->orderByDesc('id');

        return $query;
    }

    /**
     * @param  array{keyword?: ?string, start_date?: ?string, end_date?: ?string, booking_status?: ?string, payment_status?: ?string}  $filters
     * @return Builder<WeddingBooking>
     */
    public function forExport(array $filters): Builder
    {
        $query = $this->build($filters);

        $query->with(['wedding.creators', 'days.weddingDay', 'payment'])
            ->orderByDesc('id');

        return $query;
    }

    /**
     * @param  array{keyword?: ?string, start_date?: ?string, end_date?: ?string, booking_status?: ?string, payment_status?: ?string}  $filters
     * @return array{total_bookings: int, pending: int, confirmed: int, cancelled: int, total_revenue: float, wedding_bookings_count: int, total_travelers: int, total_amount: float, total_platform_fee: float, total_payout_amount: float, bookings_by_status: array<string, int>}
     */
    public function stats(array $filters): array
    {
        $query = $this->build($filters);
        $totals = (clone $query)
            ->selectRaw('count(*) as bookings_count')
            ->selectRaw('coalesce(sum(number_of_travelers), 0) as total_travelers')
            ->selectRaw('coalesce(sum(total_amount), 0) as total_amount')
            ->first();

        $bookingsByStatus = (clone $query)
            ->select('status')
            ->selectRaw('count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn (mixed $count): int => (int) $count)
            ->all();

        $revenue = (clone $query)
            ->whereIn('status', [WeddingBooking::STATUS_CONFIRMED, WeddingBooking::STATUS_COMPLETED])
            ->whereHas('payment', function (Builder $paymentQuery): void {
                $paymentQuery->where('status', Payment::STATUS_SUCCEEDED);
            })
            ->selectRaw('coalesce(sum(total_amount), 0) as paid_amount')
            ->selectRaw('coalesce(sum(platform_fee), 0) as platform_fee')
            ->first();

        return [
            'total_bookings' => (int) $totals->bookings_count,
            'pending' => $bookingsByStatus[WeddingBooking::STATUS_PENDING] ?? 0,
            'confirmed' => $bookingsByStatus[WeddingBooking::STATUS_CONFIRMED] ?? 0,
            'cancelled' => $bookingsByStatus[WeddingBooking::STATUS_CANCELLED] ?? 0,
            'total_revenue' => (float) $revenue->paid_amount,
            'wedding_bookings_count' => (int) $totals->bookings_count,
            'total_travelers' => (int) $totals->total_travelers,
            'total_amount' => (float) $totals->total_amount,
            'total_platform_fee' => (float) $revenue->platform_fee,
            'total_payout_amount' => (float) $revenue->paid_amount - (float) $revenue->platform_fee,
            'bookings_by_status' => $bookingsByStatus,
        ];
    }
}
