<?php

namespace App\Http\Controllers;

use App\Http\Resources\WeddingListResource;
use App\Models\Wedding;
use App\Models\WeddingDay;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Throwable;

class WeddingListController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->integer('per_page', 15);
        $today = today();

        try {
        $weddings = Wedding::whereHas('days', function (Builder $query) use ($today): void {
                $query->whereDate('wedding_day_date', '>=', $today);
            })
            ->with([
                'creators',
                'thumbnail',
                'days' => function (HasMany $query) use ($today): void {
                    $query->whereDate('wedding_day_date', '>=', $today);
                },
                'days.events',
            ])
            ->orderBy(
                WeddingDay::select('wedding_day_date')
                    ->whereColumn('wedding_days.wedding_id', 'weddings.id')
                    ->whereDate('wedding_day_date', '>=', $today)
                    ->orderBy('wedding_day_date', 'asc')
                    ->limit(1),
                'asc'
            )
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

}
