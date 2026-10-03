<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWeddingBookingRequest;
use App\Http\Resources\WeddingBookingResource;
use App\Mail\BookingInvoiceMail;
use App\Models\Payment;
use App\Models\Wedding;
use App\Models\WeddingBooking;
use App\Services\InvitationPdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use Symfony\Component\HttpFoundation\Response;

class WeddingBookingController extends Controller
{
    /**
     * Display a listing of the authenticated user's bookings.
     *
     * Optionally scoped to a single wedding via the `wedding_id` query
     * parameter. Pass `limit` to fetch only the N latest bookings without
     * pagination (e.g. `limit=1` for the latest booking).
     */
    public function index(Request $request): JsonResponse
    {
        // $isHost = $request->boolean('is_host');

        $isHost = $request->user()->is_host;

        $query = WeddingBooking::query()
            ->when(
                $isHost,
                // Host view: bookings made on weddings owned by the authenticated user.
                fn ($query) => $query->whereHas(
                    'wedding',
                    fn ($weddingQuery) => $weddingQuery->where('user_id', $request->user()->id)
                ),
                fn ($query) => $query->where('user_id', $request->user()->id)
            )
            ->when(
                $request->filled('wedding_id'),
                fn ($query) => $query->where('wedding_id', $request->integer('wedding_id'))
            )
            ->with([
                'wedding.images',
                'wedding.creators',
                'wedding.days',
                'wedding.thumbnail',
                'wedding.days.events',
                'days.weddingDay',
                'payment',
                'days',
            ])
            ->orderByDesc('id');

        if ($request->filled('limit')) {
            $bookings = $query->limit(max(1, $request->integer('limit')))->get();

            return response()->json([
                'status' => true,
                'data' => WeddingBookingResource::collection($bookings),
                'message' => 'Bookings retrieved successfully.',
            ]);
        }

        $bookings = $query->paginate($request->integer('per_page', 15));

        return response()->json([
            'status' => true,
            'data' => WeddingBookingResource::collection($bookings),
            'pagination' => [
                'current_page' => $bookings->currentPage(),
                'last_page' => $bookings->lastPage(),
                'per_page' => $bookings->perPage(),
                'total' => $bookings->total(),
                'from' => $bookings->firstItem(),
                'to' => $bookings->lastItem(),
                'next_page_url' => $bookings->nextPageUrl(),
                'prev_page_url' => $bookings->previousPageUrl(),
            ],
            'message' => 'Bookings retrieved successfully.',
        ]);
    }

    /**
     * Display aggregate wedding and booking statistics for the
     * authenticated user.
     *
     * Pass `is_host=1` to scope the stats to bookings made on weddings the
     * user owns, which also includes the host's payout figures (the amount
     * owed to the host after the platform fee is withheld).
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        $isHost = $user->is_host;

        $weddingsCount = $user->weddings()->count();

        $bookingQuery = WeddingBooking::query()
            ->when(
                $isHost,
                fn ($query) => $query->whereHas(
                    'wedding',
                    fn ($weddingQuery) => $weddingQuery->where('user_id', $user->id)
                ),
                fn ($query) => $query->where('user_id', $user->id)
            );

        $bookingStats = (clone $bookingQuery)
            ->selectRaw('count(*) as bookings_count')
            ->selectRaw('coalesce(sum(number_of_travelers), 0) as total_travelers')
            ->selectRaw('coalesce(sum(total_amount), 0) as total_amount')
            ->first();

        /*
         * Only confirmed/completed bookings represent money that has
         * actually been paid, so the platform fee/payout split is scoped
         * to those instead of every booking (which may still be pending).
         */
        $payoutStats = (clone $bookingQuery)
            ->whereIn('status', [WeddingBooking::STATUS_CONFIRMED, WeddingBooking::STATUS_COMPLETED])
            ->selectRaw('coalesce(sum(total_amount), 0) as paid_amount')
            ->selectRaw('coalesce(sum(platform_fee), 0) as platform_fee')
            ->first();

        $totalPlatformFee = (float) $payoutStats->platform_fee;
        $totalPayoutAmount = (float) $payoutStats->paid_amount - $totalPlatformFee;

        return response()->json([
            'status' => true,
            'data' => [
                'registered_weddings_count' => $weddingsCount,
                'wedding_bookings_count' => (int) $bookingStats->bookings_count,
                'total_travelers' => (int) $bookingStats->total_travelers,
                'total_amount' => (float) $bookingStats->total_amount,
                'total_platform_fee' => $totalPlatformFee,
                'total_payout_amount' => $totalPayoutAmount,
            ],
            'message' => 'Stats retrieved successfully.',
        ]);
    }

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
         * Create the booking, Stripe PaymentIntent and payment record inside
         * one transaction. If payWith() throws, the booking is rolled back
         * too, so a failed Stripe call never leaves an orphaned booking row.
         */
        [$booking, $payment] = DB::transaction(
            function () use ($request, $wedding, $days, $validated, $pricing) {
                $booking = $this->createBooking(
                    $request,
                    $wedding,
                    $days,
                    $validated,
                    $pricing
                );

                // $payment = $request->user()->pay(
                //     $pricing['stripe_amount']
                // );

                $payment = $request->user()->payWith(
                    $pricing['stripe_amount'],
                    ['card'],
                    [
                        'metadata' => [
                            'booking_id' => (string) $booking->id,
                            'user_id' => (string) $request->user()->id,
                        ],
                    ]
                );

                $booking->payment()->create([
                    'provider' => 'stripe',
                    'payment_intent_id' => $payment->id,
                    'client_secret' => $payment->client_secret,
                    'status' => Payment::STATUS_PENDING,
                    'amount' => $pricing['stripe_amount'],
                    'currency' => $pricing['currency'],
                ]);

                return [$booking, $payment];
            }
        );

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
     * Verify a Stripe PaymentIntent and synchronize the booking payment.
     *
     * The payment_intent_id comes from the browser/Stripe redirect, so it is
     * treated only as an identifier. The actual payment state is retrieved
     * directly from Stripe and checked against the authenticated user,
     * booking, amount, currency and server-created Stripe metadata.
     */
    public function verifyPayment(
        Request $request,
        WeddingBooking $booking,
        InvitationPdfService $pdfService
    ): JsonResponse {
        $validated = $request->validate([
            'payment_intent_id' => [
                'nullable',
                'string',
                'regex:/^pi_[A-Za-z0-9]+$/',
            ],
        ]);

        $user = $request->user();

        if ((int) $booking->user_id !== (int) $user->id) {
            abort(403);
        }

        if (! $booking->payment) {
            return response()->json([
                'success' => false,
                'status' => 'failed',
                'message' => 'No payment record exists for this booking.',
            ], 404);
        }

        $payment = $booking->payment;

        /*
         * The URL normally contains payment_intent. If it does not, use the
         * server-stored PaymentIntent ID for this booking. This keeps the
         * result page resilient while never trusting a browser-supplied ID.
         */
        $paymentIntentId = $validated['payment_intent_id'] ?? null;

        if (! $paymentIntentId) {
            return response()->json([
                'success' => false,
                'status' => 'failed',
                'message' => 'Payment intent ID is required.',
            ], 422);
        }

        /*
         * The PaymentIntent must be the exact one created for this booking.
         * This prevents a user from submitting somebody else's PaymentIntent.
         */
        if ($payment->payment_intent_id !== $paymentIntentId) {
            return response()->json([
                'success' => false,
                'status' => 'failed',
                'message' => 'The payment does not belong to this booking.',
            ], 403);
        }

        try {
            $paymentIntent = $user->stripe()
                ->paymentIntents
                ->retrieve($paymentIntentId);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'status' => 'failed',
                'message' => 'We could not verify the payment with Stripe.',
            ], 502);
        }

        /*
        * 6. Verify metadata.
        */
        $metadataBookingId = (string) (
            $paymentIntent->metadata['booking_id'] ?? ''
        );

        $metadataUserId = (string) (
            $paymentIntent->metadata['user_id'] ?? ''
        );

        if (
            $metadataBookingId !== (string) $booking->id ||
            $metadataUserId !== (string) $user->id
        ) {
            return response()->json([
                'success' => false,
                'status' => 'failed',
                'message' => 'The payment could not be matched to this booking.',
            ], 403);
        }

        /*
         * Verify amount and currency against the values calculated by our
         * backend when the booking was created.
         */
        $expectedAmount = (int) round(
            ((float) $booking->total_amount) * 100
        );

        $expectedCurrency = strtolower((string) $booking->currency);

        if (
            (int) $paymentIntent->amount !== $expectedAmount ||
            strtolower((string) $paymentIntent->currency) !== $expectedCurrency
        ) {
            return response()->json([
                'success' => false,
                'status' => 'failed',
                'message' => 'The payment amount or currency does not match the booking.',
            ], 409);
        }

        $stripeStatus = (string) $paymentIntent->status;

        /*
         * Only Stripe's authoritative status can move the booking to paid.
         * The frontend/return_url never does this.
         */
        if ($stripeStatus === 'succeeded') {
            // Captured before the update so the invoice email only fires once, on the first confirmation.
            $wasPending = $booking->status === WeddingBooking::STATUS_PENDING;

            DB::transaction(function () use ($booking, $payment) {
                $payment->update([
                    'status' => Payment::STATUS_SUCCEEDED,
                    'paid_at' => $payment->paid_at ?? now(),
                ]);

                /*
                 * Do not overwrite a newer/terminal booking state blindly.
                 * A successful payment should confirm a booking that is still
                 * waiting for payment.
                 */
                if ($booking->status === WeddingBooking::STATUS_PENDING) {
                    $booking->update([
                        'status' => WeddingBooking::STATUS_CONFIRMED,
                    ]);
                }
            });

            $booking->load([
                'wedding.images',
                'wedding.creators',
                'wedding.days',
                'wedding.days.events',
                'days.weddingDay',
                'payment',
            ]);

            // if ($wasPending) {
            $this->sendBookingInvoiceEmail($booking, $pdfService);
            // }

            return response()->json([
                'success' => true,
                'message' => 'Payment verified successfully.',
                'status' => Payment::STATUS_SUCCEEDED,
                'booking_status' => WeddingBooking::STATUS_CONFIRMED,
                'data' => new WeddingBookingResource($booking),
            ], 200);
        }

        if ($stripeStatus === 'processing') {
            if ($payment->status !== Payment::STATUS_SUCCEEDED) {
                $payment->update([
                    'status' => Payment::STATUS_PROCESSING,
                ]);
            }
            $booking->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Your payment is still being processed.',
                'status' => 'processing',
                'data' => new WeddingBookingResource(
                    $booking->load([
                        'wedding.images',
                        'wedding.creators',
                        'wedding.days',
                        'wedding.days.events',
                        'days.weddingDay',
                        'payment',
                    ])
                ),
            ], 200);
        }

        /*
     * PAYMENT REQUIRES CUSTOMER ACTION
     */
        if ($stripeStatus === 'requires_action') {
            return response()->json([
                'success' => false,
                'status' => 'requires_action',
                'stripe_status' => $stripeStatus,
                'message' => 'Additional payment authentication is required.',
                'data' => new WeddingBookingResource(
                    $booking->fresh()->load([
                        'wedding.images',
                        'wedding.creators',
                        'wedding.days',
                        'wedding.days.events',
                        'days.weddingDay',
                        'payment',
                    ])
                ),
            ], 422);
        }

        /*
     * PAYMENT CANCELED
     */
        if ($stripeStatus === 'canceled') {
            if ($payment->status !== Payment::STATUS_SUCCEEDED) {
                $payment->update([
                    'status' => Payment::STATUS_CANCELLED,
                ]);
            }

            $booking->update([
                'status' => WeddingBooking::STATUS_CANCELLED,
            ]);

            return response()->json([
                'success' => false,
                'status' => 'cancelled',
                'stripe_status' => $stripeStatus,
                'message' => 'The payment was canceled. Please try again.',
                'data' => new WeddingBookingResource(
                    $booking->fresh()->load([
                        'wedding.images',
                        'wedding.creators',
                        'wedding.days',
                        'wedding.days.events',
                        'days.weddingDay',
                        'payment',
                    ])
                ),
            ], 422);
        }

        /*
         * These states mean the payment is not successful. Do not confirm the
         * booking. requires_payment_method is the normal failed/retry state.
         */
        $payment->update([
            'status' => Payment::STATUS_FAILED,
        ]);

        return response()->json([
            'success' => false,
            'status' => 'failed',
            'message' => match ($stripeStatus) {
                'requires_payment_method' => 'The payment was not completed. Please try again.',
                'canceled' => 'The payment was canceled. Please try again.',
                default => 'The payment is not complete.',
            },
            'data' => new WeddingBookingResource(
                $booking->fresh()->load([
                    'wedding.images',
                    'wedding.creators',
                    'wedding.days',
                    'wedding.days.events',
                    'days.weddingDay',
                    'payment',
                ])
            ),
        ], 422);
    }

    /**
     * Display a booking.
     */
    public function show(
        Request $request,
        Wedding $wedding,
        WeddingBooking $booking
    ): WeddingBookingResource {
        $this->authorize(
            'view',
            $booking
        );
        if ($booking->user_id !== $request->user()->id && $booking->wedding->user_id !== $request->user()->id) {
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
     * Display detailed information about a booking.
     */
    public function bookingDetail(
        Request $request,
        WeddingBooking $booking
    ): WeddingBookingResource {

        $booking->load([
            'wedding.images',
            'wedding.thumbnail',
            'wedding.creators',
            'wedding.days',
            'wedding.days.events',
            'days.weddingDay',
            'days.weddingDay.events',
            'payment',
        ]);

        if (($booking->wedding->user_id !== $request->user()->id) && ($booking->user_id !== $request->user()->id)) {
            abort(403);
        }

        return new WeddingBookingResource($booking);
    }

    /**
     * Email the invoice and invitation card for a newly confirmed booking.
     *
     * Failures are reported but never bubble up, since the payment has
     * already been verified with Stripe by the time this runs.
     */
    private function sendBookingInvoiceEmail(
        WeddingBooking $booking,
        InvitationPdfService $pdfService
    ): void {
        try {
            $invitationData = $pdfService->getInvitationData($booking);
            $invitationPdf = $pdfService->generate($invitationData);

            Mail::to($booking->email)->send(
                new BookingInvoiceMail($booking, $invitationData, $invitationPdf)
            );

            Log::info('Booking invoice email sent.', [
                'booking_id' => $booking->id,
                'email' => $booking->email,
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            Log::error('Booking invoice email failed to send.', [
                'booking_id' => $booking->id,
                'email' => $booking->email,
                'error' => $exception->getMessage(),
            ]);
        }
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
     *
     * The guest is only charged the subtotal (plus any payment fee). The
     * platform fee is a percentage of the subtotal that is withheld from
     * the amount owed to the wedding host and kept by the platform/admin;
     * it is never added on top of what the guest pays.
     */
    private function calculatePricing(
        Wedding $wedding,
        int $travelerCount
    ): array {
        $pricePerPerson = (float) config('services.wedding.price_per_person');

        $subtotal = $pricePerPerson * $travelerCount;

        $platformFeePercentage = (float) config('services.wedding.platform_fee_percentage');
        $platformFee = round($subtotal * ($platformFeePercentage / 100), 2);
        $paymentFee = 0;

        $totalAmount =
            $subtotal +
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
            'status' => WeddingBooking::STATUS_PENDING,
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

    /**
     * Handle Stripe webhook events.
     *
     * This endpoint must NOT use auth:sanctum.
     * Stripe authenticates the request using the webhook signature.
     */
    public function webhook(Request $request): Response
    {
        $payload = $request->getContent();

        $signature = $request->header('Stripe-Signature');

        if (! $signature) {
            return response('Missing Stripe signature.', 400);
        }

        try {
            $event = Webhook::constructEvent(
                $payload,
                $signature,
                config('services.stripe.webhook_secret')
            );
        } catch (\UnexpectedValueException $e) {
            // Invalid JSON payload.
            return response('Invalid payload.', 400);
        } catch (SignatureVerificationException $e) {
            // Invalid Stripe signature.
            return response('Invalid signature.', 400);
        }

        /*
         * We are interested in PaymentIntent events
         * for the wedding booking payment flow.
         */
        if (
            ! in_array($event->type, [
                'payment_intent.succeeded',
                'payment_intent.payment_failed',
                'payment_intent.processing',
            ], true)
        ) {
            return response()->json([
                'received' => true,
            ]);
        }

        $paymentIntent = $event->data->object;

        /*
         * Find our booking using the PaymentIntent ID
         * stored in our database.
         */
        $booking = WeddingBooking::whereHas('payment', function ($query) use ($paymentIntent) {
            $query->where(
                'payment_intent_id',
                $paymentIntent->id
            );
        })
            ->with('payment')
            ->first();

        /*
         * Stripe may send an event for a PaymentIntent
         * that our application does not know about.
         */
        if (! $booking) {
            return response()->json([
                'received' => true,
                'message' => 'PaymentIntent is not associated with a booking.',
            ]);
        }

        $payment = $booking->payment;

        /*
         * Verify the Stripe amount against our database.
         *
         * Never trust an amount coming from the frontend.
         */
        if ((int) $paymentIntent->amount !== (int) $payment->amount) {
            return response()->json([
                'received' => false,
                'message' => 'Payment amount mismatch.',
            ], 400);
        }

        /*
         * Verify currency.
         */
        if (
            strtolower((string) $paymentIntent->currency) !==
            strtolower((string) $payment->currency)
        ) {
            return response()->json([
                'received' => false,
                'message' => 'Payment currency mismatch.',
            ], 400);
        }

        /*
         * Optional metadata validation.
         *
         * Do NOT make this mandatory if your existing
         * PaymentIntents were created without metadata.
         */
        $metadata = $paymentIntent->metadata ?? null;

        if (
            isset($metadata->booking_id) &&
            (string) $metadata->booking_id !== (string) $booking->id
        ) {
            return response()->json([
                'received' => false,
                'message' => 'Payment booking metadata mismatch.',
            ], 400);
        }

        if (
            isset($metadata->user_id) &&
            (string) $metadata->user_id !== (string) $booking->user_id
        ) {
            return response()->json([
                'received' => false,
                'message' => 'Payment user metadata mismatch.',
            ], 400);
        }

        /*
         * Update our application according to the
         * Stripe PaymentIntent status.
         */
        switch ($event->type) {
            case 'payment_intent.succeeded':

                DB::transaction(function () use ($booking, $payment) {

                    /*
                     * Idempotency:
                     * Do not repeatedly perform the same
                     * successful update.
                     */
                    $payment->update([
                        'status' => Payment::STATUS_SUCCEEDED,
                        'paid_at' => $payment->paid_at ?? now(),
                    ]);

                    $booking->update([
                        'status' => WeddingBooking::STATUS_CONFIRMED,
                    ]);
                });

                break;

            case 'payment_intent.processing':

                /*
                 * Payment has not failed, but Stripe is
                 * still processing it.
                 */
                if ($payment->status !== Payment::STATUS_SUCCEEDED) {
                    $payment->update([
                        'status' => Payment::STATUS_PROCESSING,
                    ]);

                    $booking->update([
                        'status' => WeddingBooking::STATUS_PENDING,
                    ]);
                }

                break;

            case 'payment_intent.payment_failed':

                /*
                 * Don't overwrite an already successful
                 * payment with a later failed event.
                 */
                if ($payment->status !== Payment::STATUS_SUCCEEDED) {
                    $payment->update([
                        'status' => Payment::STATUS_FAILED,
                    ]);

                    $booking->update([
                        'status' => WeddingBooking::STATUS_FAILED,
                    ]);
                }

                break;
        }

        return response()->json([
            'received' => true,
        ]);
    }
}
