<?php

namespace App\Http\Controllers\Admin;

use App\Exports\WeddingsExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\WeddingIndexRequest;
use App\Http\Resources\WeddingDetailResource;
use App\Http\Resources\WeddingListResource;
use App\Models\Wedding;
use App\Queries\WeddingQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class WeddingController extends Controller
{
    public function __construct(
        private readonly WeddingQuery $weddingQuery,
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(WeddingIndexRequest $request): JsonResponse
    {
        $filters = $request->validated();

        $query = $this->weddingQuery->forList($filters);

        if (isset($filters['limit'])) {
            $weddings = $query->limit($filters['limit'])->get();

            return response()->json([
                'status' => true,
                'data' => WeddingListResource::collection($weddings),
                'pagination' => null,
                'message' => 'Weddings retrieved successfully.',
            ]);

        }

        $perPage = $filters['per_page'] ?? 15;

        $weddings = $query->paginate($perPage);

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
    }

    /**
     * Export the same filtered dataset used by the list.
     */
    public function export(
        WeddingIndexRequest $request,
    ): BinaryFileResponse {
        $filters = $request->validated();

        $fileName = sprintf(
            'weddings-%s.xlsx',
            now()->format('Y-m-d_His'),
        );

        return Excel::download(
            new WeddingsExport(
                $this->weddingQuery->forExport($filters),
            ),
            $fileName,
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Wedding $wedding): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => new WeddingDetailResource(
                $wedding->load([
                    'creators',
                    'images',
                    'thumbnail',
                    'days.events',
                ])
            ),
            'message' => 'Wedding details retrieved successfully.',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Wedding $wedding): JsonResponse
    {

        try {
            $connection = $wedding->getConnection();

            $connection->statement('SET FOREIGN_KEY_CHECKS = 0');

            try {
                $connection->transaction(function () use ($connection, $wedding): void {
                    $dayIds = $connection->table('wedding_days')
                        ->where('wedding_id', $wedding->id)
                        ->pluck('id');

                    if ($dayIds->isNotEmpty()) {
                        $connection->table('wedding_day_events')
                            ->whereIn('wedding_day_id', $dayIds)
                            ->delete();
                    }

                    $connection->table('wedding_days')
                        ->where('wedding_id', $wedding->id)
                        ->delete();

                    $wedding->images()->each(function ($image): void {
                        if ($image->image) {
                            Storage::disk($image->disk ?? 'public')
                                ->delete($image->image);
                        }
                    });

                    $connection->table('wedding_images')
                        ->where('wedding_id', $wedding->id)
                        ->delete();
                    $connection->table('wedding_creators')
                        ->where('wedding_id', $wedding->id)
                        ->delete();
                    $connection->table('weddings')
                        ->where('id', $wedding->id)
                        ->delete();
                });
            } finally {
                $connection->statement('SET FOREIGN_KEY_CHECKS = 1');
            }

            return response()->json([
                'status' => true,
                'data' => null,
                'message' => 'Wedding deleted successfully.',
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'status' => false,
                'data' => null,
                'message' => $e->getMessage(),
                // 'message' => 'Unable to delete wedding. Please try again.',
            ], 500);
        }
    }
}
