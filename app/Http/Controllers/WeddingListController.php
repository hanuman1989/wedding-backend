<?php

namespace App\Http\Controllers;

use App\Http\Resources\WeddingListResource;
use App\Models\Wedding;
use App\Models\WeddingDay;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class WeddingListController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date'],
            'latitude' => ['sometimes', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'numeric', 'between:-180,180'],
            'food_observance' => ['sometimes', 'nullable', 'string'],
        ]);

        $perPage = $request->integer('per_page', 15);
        $startDate = $validated['date'] ?? today()->toDateString();
        $endDate = $validated['end_date'] ?? null;
        $latitude = isset($validated['latitude']) ? (float) $validated['latitude'] : null;
        $longitude = isset($validated['longitude']) ? (float) $validated['longitude'] : null;
        $hasCoordinates = $latitude !== null && $longitude !== null;
        $foodObservance = $validated['food_observance'] ?? null;

        try {
            $weddings = Wedding::whereHas('days', function (Builder $query) use ($startDate, $endDate): void {
                $query->whereDate('wedding_day_date', '>=', $startDate);

                if ($endDate) {
                    $query->whereDate('wedding_day_date', '<=', $endDate);
                }
            })
                ->with([
                    'creators',
                    'thumbnail',
                    'days' => function (HasMany $query) use ($startDate, $endDate): void {
                        $query->whereDate('wedding_day_date', '>=', $startDate);

                        if ($endDate) {
                            $query->whereDate('wedding_day_date', '<=', $endDate);
                        }
                    },
                    'days.events',
                ])
                ->when(
                    $hasCoordinates,
                    function (Builder $query) use ($latitude, $longitude, $startDate, $endDate): void {
                        /*
                         * A correlated subquery returns one scalar per wedding,
                         * so it cannot introduce duplicate wedding rows.
                         */
                        $query->addSelect([
                            'distance_km' => $this->nearestDayDistanceQuery($latitude, $longitude, $startDate, $endDate),
                        ])->orderBy(
                            $this->nearestDayDistanceQuery($latitude, $longitude, $startDate, $endDate),
                            'asc'
                        );
                    },
                    function (Builder $query) use ($startDate, $endDate): void {
                        $query->orderBy(
                            WeddingDay::select('wedding_day_date')
                                ->whereColumn('wedding_days.wedding_id', 'weddings.id')
                                ->whereDate('wedding_day_date', '>=', $startDate)
                                ->when($endDate, fn ($q) => $q->whereDate('wedding_day_date', '<=', $endDate))
                                ->orderBy('wedding_day_date', 'asc')
                                ->limit(1),
                            'asc'
                        );
                    }
                )
                ->when(filled($foodObservance), function (Builder $query) use ($foodObservance): void {
                    $query->where('food_observance', $foodObservance);
                })
                ->orderBy('id', 'asc')
                ->paginate($perPage);

            return response()->json([
                'status' => true,
                'data' => WeddingListResource::collection($weddings),
                'pagination' => [
                    'current_page' => $weddings->currentPage(),
                    'last_page' => $weddings->lastPage(),
                    'per_page' => $weddings->perPage(),
                    'total' => $weddings->total(),
                    'from' => $weddings->firstItem(),
                    'to' => $weddings->lastItem(),
                    'next_page_url' => $weddings->nextPageUrl(),
                    'prev_page_url' => $weddings->previousPageUrl(),
                ],
                'message' => 'Weddings retrieved successfully.',
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'status' => false,
                'data' => null,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function popular(Request $request): JsonResponse
    {
        $perPage = $request->integer('per_page', 20);
        $today = today();

        try {
            $weddings = Wedding::whereHas('days', function (Builder $query) use ($today): void {
                $query->whereDate('wedding_day_date', '>=', $today);
            })
                ->with([
                    'creators',
                    'thumbnail',
                    'days' => function (HasMany $query) use ($today): void {
                        $query->whereDate('wedding_day_date', '>=', $today)
                            ->orderBy('wedding_day_date', 'asc');
                    },
                    'days.events',
                ])
                ->orderByDesc('is_popular')
                ->orderBy(
                    WeddingDay::select('wedding_day_date')
                        ->whereColumn('wedding_days.wedding_id', 'weddings.id')
                        ->whereDate('wedding_day_date', '>=', $today)
                        ->orderBy('wedding_day_date', 'asc')
                        ->limit(1),
                    'asc'
                )
                ->paginate($perPage);

            return response()->json([
                'status' => true,
                'data' => WeddingListResource::collection($weddings),
                'pagination' => [
                    'current_page' => $weddings->currentPage(),
                    'last_page' => $weddings->lastPage(),
                    'per_page' => $weddings->perPage(),
                    'total' => $weddings->total(),
                    'from' => $weddings->firstItem(),
                    'to' => $weddings->lastItem(),
                    'next_page_url' => $weddings->nextPageUrl(),
                    'prev_page_url' => $weddings->previousPageUrl(),
                ],
                'message' => 'Popular weddings retrieved successfully.',
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'status' => false,
                'data' => null,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Build a correlated subquery for the shortest Haversine distance (in
     * kilometers) between the given coordinates and any wedding day
     * matching the date range, scoped per wedding.
     */
    private function nearestDayDistanceQuery(
        float $latitude,
        float $longitude,
        string $startDate,
        ?string $endDate
    ) {
        $haversine = '6371 * acos(cos(radians(?)) * cos(radians(wedding_days.latitude)) * cos(radians(wedding_days.longitude) - radians(?)) + sin(radians(?)) * sin(radians(wedding_days.latitude)))';

        return WeddingDay::selectRaw($haversine, [$latitude, $longitude, $latitude])
            ->whereColumn('wedding_days.wedding_id', 'weddings.id')
            ->whereDate('wedding_day_date', '>=', $startDate)
            ->when($endDate, fn ($query) => $query->whereDate('wedding_day_date', '<=', $endDate))
            ->orderByRaw($haversine, [$latitude, $longitude, $latitude])
            ->limit(1);
    }
}
