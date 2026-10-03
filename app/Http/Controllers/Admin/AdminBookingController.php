<?php

namespace App\Http\Controllers\Admin;

use App\Exports\BookingsExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BookingIndexRequest;
use App\Http\Resources\WeddingBookingResource;
use App\Models\User;
use App\Models\Wedding;
use App\Models\WeddingBooking;
use App\Queries\BookingQuery;
use App\Services\InvitationPdfService;
use Illuminate\Http\JsonResponse;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class AdminBookingController extends Controller
{
    public function __construct(
        private readonly BookingQuery $bookingQuery,
    ) {}

    /**
     * Display filtered bookings and statistics for the admin.
     */
    public function index(BookingIndexRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $query = $this->bookingQuery->forList($filters);
        $stats = $this->bookingQuery->stats($filters);

        if (isset($filters['limit'])) {
            $bookings = $query->limit($filters['limit'])->get();

            return response()->json([
                'status' => true,
                'data' => WeddingBookingResource::collection($bookings),
                'pagination' => null,
                'stats' => $stats,
                'message' => 'Bookings retrieved successfully.',
            ]);
        }

        $bookings = $query->paginate($filters['per_page'] ?? 15);

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
            'stats' => $stats,
            'message' => 'Bookings retrieved successfully.',
        ]);
    }

    /**
     * Export all bookings matching the list filters.
     */
    public function export(BookingIndexRequest $request): BinaryFileResponse
    {
        $fileName = sprintf('bookings-%s.xlsx', now()->format('Y-m-d_His'));

        return Excel::download(
            new BookingsExport($this->bookingQuery->forExport($request->validated())),
            $fileName,
        );
    }

    /**
     * Display the specified booking.
     */
    public function show(WeddingBooking $booking): JsonResponse
    {
        try {
            $booking->load([
                'wedding.images',
                'wedding.creators',
                'wedding.days',
                'wedding.thumbnail',
                'wedding.days.events',
                'days.weddingDay',
                'payment',
                'days',
            ]);

            return response()->json([
                'status' => true,
                'data' => new WeddingBookingResource($booking),
                'message' => 'Booking details retrieved successfully.',
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'status' => false,
                'data' => null,
                'message' => 'Unable to retrieve booking details. Please try again.',
            ], 500);
        }
    }

    /**
     * Display dashboard counts and filtered booking and revenue statistics.
     */
    public function stats(BookingIndexRequest $request): JsonResponse
    {
        $usersCount = User::query()->count();
        $hostsCount = User::query()->where('is_host', true)->count();

        $weddingsCount = Wedding::query()->count();

        $bookingStats = $this->bookingQuery->stats($request->validated());

        return response()->json([
            'status' => true,
            'data' => [
                'users_count' => $usersCount ?? 0,
                'hosts_count' => $hostsCount ?? 0,
                'registered_weddings_count' => $weddingsCount,
                ...$bookingStats,
            ],
            'message' => 'Stats retrieved successfully.',
        ]);
    }

    public function download(
        WeddingBooking $booking,
        InvitationPdfService $pdfService
    ) {

        $data = $pdfService->getInvitationData(
            $booking
        );

        $pdf = $pdfService->generate($data);

        $filename =
            'wedding-invitation-'.
            $booking->id.
            '.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',

            'Content-Disposition' => 'attachment; filename="'.
                $filename.
                '"',

            'Content-Length' => strlen($pdf),
        ]);
    }
}
