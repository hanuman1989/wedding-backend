<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexWeddingsRequest;
use App\Http\Requests\StoreWeddingRequest;
use App\Http\Requests\SubmitWeddingRequest;
use App\Http\Requests\UpdateWeddingRequest;
use App\Http\Resources\WeddingListResource;
use App\Http\Resources\WeddingResource;
use App\Models\Wedding;
use App\Services\WeddingSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Throwable;


class WeddingController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(IndexWeddingsRequest $request): JsonResponse
    {
        $perPage = $request->integer('per_page', 15);
        try {
        $weddings = Wedding::where('user_id', $request->user()->id)
            ->with([
                'creators',
                'thumbnail',
                'days',
                'days.events',
            ])
            ->orderBy('id', 'asc')
            ->paginate($perPage);

        return response()->json([
            'status' => true,
            'data' => WeddingListResource::collection($weddings),
            'message' => 'Weddings retrieved successfully.',
        ]);
    } catch (Throwable $e) {
        report($e);

        return response()->json([
            'status' => false,
            'data' => null,
            'message' => 'Something went wrong. Please try again.',
        ], 500);
    }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreWeddingRequest $request): JsonResponse
    {
        try {
            $wedding = Wedding::create([
                ...$request->validated(),
                'user_id' => $request->user()->id,
                'status' => Wedding::STATUS_DRAFT,
            ]);

            if (! $request->user()->is_host) {
                $request->user()->update(['is_host' => true]);
            }

            $response = [
                'status' => true,
                'data' => new WeddingResource($wedding),
                'message' => 'Wedding created successfully.',
            ];

            return response()->json($response, 201);

        } catch (Throwable $e) {
            report($e);

            $response = [
                'status' => false,
                'data' => null,
                'message' => 'Unable to save wedding details. Please try again.',
            ];

            return response()->json($response, 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Wedding $wedding): JsonResponse
    {

        return response()->json([
            'status' => true,
            'data' => new WeddingResource($wedding->load('creators', 'images', 'days.events')),
            'message' => 'Wedding details retrieved successfully.',
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateWeddingRequest $request, Wedding $wedding): JsonResponse
    {
        if ($request->user()->id !== $wedding->user_id) {
            return response()->json([
                'status' => false,
                'data' => null,
                'message' => 'Wedding not found.',
            ], 404);
        }
        try {
            $wedding->update($request->validated());
            $wedding->refresh();
            if (! $request->user()->is_host) {
                $request->user()->update(['is_host' => true]);
            }

            return response()->json([
                'status' => true,
                'data' => new WeddingResource($wedding->load('images', 'days.events')),
                'message' => 'Wedding updated successfully.',
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'status' => false,
                'data' => null,
                'message' => 'Unable to update wedding details. Please try again.',
            ], 500);
        }
    }

    public function submit(SubmitWeddingRequest $request, Wedding $wedding, WeddingSubmissionService $submissionService): JsonResponse
    {

        $wedding = $submissionService->submit($wedding);

        return response()->json([
            'status' => true,
            'message' => 'Wedding published successfully.',
            'data' => (new WeddingResource($wedding->load('creators', 'images', 'days.events')))->resolve(),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Wedding $wedding): JsonResponse
    {
        if ($request->user()->id !== $wedding->user_id) {
            return response()->json([
                'status' => false,
                'data' => null,
                'message' => 'Wedding not found.',
            ], 404);
        }

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
        } catch (Throwable $e) {
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
