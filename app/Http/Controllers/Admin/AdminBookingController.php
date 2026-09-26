<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WeddingBooking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminBookingController extends Controller
{
    /**
     * Display platform-wide booking and platform-fee statistics for the
     * admin dashboard, i.e. how much is owed to the platform across every
     * wedding host's bookings.
     */
    public function stats(Request $request): JsonResponse
    {
        $bookingStats = WeddingBooking::query()
            ->selectRaw('count(*) as bookings_count')
            ->selectRaw('coalesce(sum(number_of_travelers), 0) as total_travelers')
            ->selectRaw('coalesce(sum(total_amount), 0) as total_amount')
            ->first();

        /*
         * Only confirmed/completed bookings represent money that has
         * actually been paid, so the platform fee/payout split is scoped
         * to those instead of every booking (which may still be pending).
         */
        $payoutStats = WeddingBooking::query()
            ->whereIn('status', [WeddingBooking::STATUS_CONFIRMED, WeddingBooking::STATUS_COMPLETED])
            ->selectRaw('coalesce(sum(total_amount), 0) as paid_amount')
            ->selectRaw('coalesce(sum(platform_fee), 0) as platform_fee')
            ->first();

        $totalPlatformFee = (float) $payoutStats->platform_fee;
        $totalHostPayoutAmount = (float) $payoutStats->paid_amount - $totalPlatformFee;

        return response()->json([
            'status' => true,
            'data' => [
                'bookings_count' => (int) $bookingStats->bookings_count,
                'total_travelers' => (int) $bookingStats->total_travelers,
                'total_amount' => (float) $bookingStats->total_amount,
                'total_platform_fee' => $totalPlatformFee,
                'total_host_payout_amount' => $totalHostPayoutAmount,
            ],
            'message' => 'Stats retrieved successfully.',
        ]);
    }
}
