<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWeddingBookingRequest;
use App\Http\Resources\WeddingBookingResource;
use App\Models\Wedding;
use App\Models\WeddingBooking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WeddingBookingController extends Controller
{
    /**
     * Create a wedding booking and initialize Stripe payment.
     */
    public function store(
        StoreWeddingBookingRequest $request,
        Wedding $wedding
    ): JsonResponse {
        $validated = $request->validated();

        /*
         * Validate wedding availability.
         */
        $this->ensureWeddingIsBookable($wedding);

        /*
         * Load and validate selected wedding days.
         */
        $days = $this->getSelectedDays(
            $wedding,
            $validated['selected_days']
        );

        /*
         * Calculate the booking amount from
         * the database.
         */
        $pricing = $this->calculatePricing(
            $wedding,
            $validated['number_of_travelers']
        );

        /*
         * Create the booking and related records.
         */
        $booking = DB::transaction(
            fn () => $this->createBooking(
                $request,
                $wedding,
                $days,
                $validated,
                $pricing
            )
        );

        /*
         * Create Stripe PaymentIntent through
         * Laravel Cashier.
         */
        $payment = $request->user()->pay(
            $pricing['stripe_amount']
        );

        /*
         * Save our application's payment record.
         */
        $booking->payment()->create([
            'provider' => 'stripe',
            'payment_intent_id' => $payment->id,
            'client_secret' => $payment->client_secret,
            'status' => 'pending',
            'amount' => $pricing['stripe_amount'],
            'currency' => $pricing['currency'],
        ]);

        return response()->json([
            'message' => 'Booking created successfully.',
            'data' => [
                'booking' => new WeddingBookingResource($booking),
                'client_secret' => $payment->client_secret,
                'payment_intent_id' => $payment->id,
            ],
        ], 201);
    }

    /**
     * Display a booking.
     */
    public function show(Request $request,
        Wedding $wedding,
        WeddingBooking $booking
    ): WeddingBookingResource {
        $this->authorize(
            'view',
            $booking
        );
        if ($booking->user_id !== $request->user()->id) {
            abort(403);
        }
        if ($booking->wedding_id !== $wedding->id) {
            abort(403);
        }
        $booking->load([
            'wedding.images',
            'wedding.creators',
            'wedding.days',
            'wedding.days.events',
            'days.weddingDay',
            'payment',
        ]);

        return new WeddingBookingResource($booking);
    }

    /**
     * Ensure the wedding can currently be booked.
     */
    private function ensureWeddingIsBookable(
        Wedding $wedding
    ): void {
        if ($wedding->status !== 'published') {
            throw ValidationException::withMessages([
                'wedding' => 'This wedding is not available for booking.',
            ]);
        }

        /*
         * If your Wedding model already has
         * wedding_expired logic, use that method here.
         */
        if ($wedding->isExpired()) {
            throw ValidationException::withMessages([
                'wedding' => 'This wedding has already expired.',
            ]);
        }
    }

    /**
     * Get selected days belonging to this wedding.
     */
    private function getSelectedDays(
        Wedding $wedding,
        array $selectedDayIds
    ) {
        $days = $wedding->days()
            ->whereKey($selectedDayIds)
            ->orderBy('wedding_day_date')
            ->get();

        /*
         * Make sure every submitted day belongs
         * to this wedding.
         */
        if ($days->count() !== count($selectedDayIds)) {
            throw ValidationException::withMessages([
                'selected_days' => 'One or more selected wedding days are invalid.',
            ]);
        }

        /*
         * Do not allow booking an expired day.
         */
        if ($days->contains(
            fn ($day) => $day->wedding_day_date?->isPast() === true
        )) {
            throw ValidationException::withMessages([
                'selected_days' => 'One or more selected wedding days have expired.',
            ]);
        }

        return $days;
    }

    /**
     * Calculate booking pricing.
     *
     * Never trust the amount sent by Next.js.
     */
    private function calculatePricing(
        Wedding $wedding,
        int $travelerCount
    ): array {
        /*
         * Replace this with your actual
         * WeddingPricing relationship.
         */
        $pricePerPerson = (float) 250;

        $subtotal = $pricePerPerson * $travelerCount;
        $platformFee = 0;
        $paymentFee = 0;

        $totalAmount =
            $subtotal +
            $platformFee +
            $paymentFee;

        $currency = strtolower(
            $wedding->currency ?? 'usd'
        );

        /*
         * Stripe uses the smallest currency unit.
         *
         * USD 250.00 => 25000
         */
        $stripeAmount = (int) round(
            $totalAmount * 100
        );

        return [
            'price_per_person' => $pricePerPerson,
            'subtotal' => $subtotal,
            'platform_fee' => $platformFee,
            'payment_fee' => $paymentFee,
            'total_amount' => $totalAmount,
            'currency' => $currency,
            'stripe_amount' => $stripeAmount,
        ];
    }

    /**
     * Create booking and all related records.
     */
    private function createBooking(
        StoreWeddingBookingRequest $request,
        Wedding $wedding,
        $days,
        array $validated,
        array $pricing
    ): WeddingBooking {
        $booking = $wedding->bookings()->create([
            'user_id' => $request->user()->id,
            'booking_number' => $this->generateBookingNumber(),
            'status' => 'pending_payment',
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'visiting_from' => $validated['visiting_from'] ?? null,
            'heard_about' => $validated['heard_about'] ?? null,
            'number_of_travelers' => $validated['number_of_travelers'],
            'price_per_person' => $pricing['price_per_person'],
            'subtotal' => $pricing['subtotal'],
            'platform_fee' => $pricing['platform_fee'],
            'payment_fee' => $pricing['payment_fee'],
            'total_amount' => $pricing['total_amount'],
            'currency' => $pricing['currency'],
            'expires_at' => now()->addMinutes(30),
        ]);

        /*
         * Create selected booking days
         * through the relationship.
         */
        $booking->days()->createMany(
            $days->map(
                fn ($day) => [
                    'wedding_day_id' => $day->id,
                ]
            )->all()
        );

        return $booking;
    }

    /**
     * Generate a unique booking number.
     */
    private function generateBookingNumber(): string
    {
        do {
            $number =
                'IWI-'.
                strtoupper(
                    Str::random(10)
                );
        } while (
            WeddingBooking::where(
                'booking_number',
                $number
            )->exists()
        );

        return $number;
    }
}
