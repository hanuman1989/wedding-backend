<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexWeddingsRequest;
use App\Http\Requests\StoreWeddingRequest;
use App\Http\Requests\SubmitWeddingRequest;
use App\Http\Requests\UpdateWeddingStepOneRequest;
use App\Http\Resources\WeddingListResource;
use App\Http\Resources\WeddingResource;
use App\Models\Wedding;
use App\Services\WeddingSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class WeddingController extends Controller
{
    public function index(IndexWeddingsRequest $request): JsonResponse
    {
        $query = $request->user()->weddings()
            ->with([
                'creators:id,wedding_id,creator_type,first_name,last_name',
                'images' => fn ($query) => $query
                    ->select(['id', 'wedding_id', 'image', 'disk', 'sort_order'])
                    ->limit(1),
                'days' => fn ($query) => $query
                    ->select(['id', 'wedding_id', 'day_number', 'wedding_day_date', 'city', 'state'])
                    ->limit(1),
            ])
            ->withCount('images')
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        $weddings = $query->paginate($request->integer('per_page', 15));

        return response()->json([
            'status' => true,
            'message' => '',
            'data' => [
                'items' => WeddingListResource::collection($weddings->items())->resolve(),
                'pagination' => [
                    'current_page' => $weddings->currentPage(),
                    'last_page' => $weddings->lastPage(),
                    'per_page' => $weddings->perPage(),
                    'total' => $weddings->total(),
                ],
            ],
        ]);
    }

    public function store(StoreWeddingRequest $request): JsonResponse
    {
        $user = $request->user();
        $wedding = DB::transaction(function () use ($request, $user): Wedding {
            $wedding = new Wedding($request->safe()->only([
                'creator_type',
                'creator_type_other',
            ]));
            $wedding->user()->associate($user);
            $wedding->status = Wedding::STATUS_DRAFT;
            $wedding->current_step = 1;
            $wedding->save();

            $wedding->creators()->create([
                'creator_type' => $request->string('creator_type')->toString(),
                'first_name' => $request->string('first_name')->toString(),
                'last_name' => $request->string('last_name')->toString(),
                'email' => $user->email,
                'phone' => $request->string('phone')->toString(),
                'fathers_name' => $request->string('fathers_name')->toString() ?: null,
                'mothers_name' => $request->string('mothers_name')->toString() ?: null,
            ]);

            $user->forceFill(['is_host' => true])->save();

            return $wedding;
        });

        return response()->json([
            'status' => true,
            'message' => 'Wedding draft created successfully.',
            'data' => (new WeddingResource($wedding->load('creators', 'images', 'days.events')))->resolve(),
        ], 201);
    }

    public function updateStepOne(UpdateWeddingStepOneRequest $request, Wedding $wedding): JsonResponse
    {
        $this->authorize('update', $wedding);

        $user = $request->user();
        $wedding = DB::transaction(function () use ($request, $user, $wedding): Wedding {
            $creatorType = $request->string('creator_type')->toString();
            $creator = $wedding->creators()
                ->where('creator_type', $wedding->creator_type)
                ->firstOrFail();

            if ($wedding->creator_type !== $creatorType) {
                if ($wedding->creators()->where('creator_type', $creatorType)->exists()) {
                    throw ValidationException::withMessages([
                        'creator_type' => 'The selected creator type is already assigned to another wedding participant.',
                    ]);
                }

                $creator->update(['creator_type' => $creatorType]);
            }

            $wedding->update([
                'creator_type' => $creatorType,
                'creator_type_other' => $creatorType === 'other'
                    ? $request->string('creator_type_other')->toString()
                    : null,
            ]);
            $creator->update([
                'first_name' => $request->string('first_name')->toString(),
                'last_name' => $request->string('last_name')->toString(),
                'email' => $user->email,
                'phone' => $request->string('phone')->toString(),
                'fathers_name' => $request->string('fathers_name')->toString() ?: null,
                'mothers_name' => $request->string('mothers_name')->toString() ?: null,
            ]);

            return $wedding;
        });

        return response()->json([
            'status' => true,
            'message' => 'Wedding details saved successfully.',
            'data' => (new WeddingResource($wedding->load('creators', 'images', 'days.events')))->resolve(),
        ]);
    }

    public function show(Wedding $wedding): JsonResponse
    {
        $this->authorize('view', $wedding);

        return response()->json([
            'status' => true,
            'message' => '',
            'data' => (new WeddingResource($wedding->load('creators', 'images', 'days.events')))->resolve(),
        ]);
    }

    public function submit(SubmitWeddingRequest $request, Wedding $wedding, WeddingSubmissionService $submissionService): JsonResponse
    {
        $this->authorize('submit', $wedding);

        $wedding = $submissionService->submit($wedding);

        return response()->json([
            'status' => true,
            'message' => 'Wedding published successfully.',
            'data' => (new WeddingResource($wedding->load('creators', 'images', 'days.events')))->resolve(),
        ]);
    }

    public function destroy(Wedding $wedding): JsonResponse
    {
        $this->authorize('delete', $wedding);

        $wedding->images()->get()->each(function ($image): void {
            Storage::disk($image->disk)->delete($image->image);
        });

        DB::transaction(function () use ($wedding): void {
            $wedding->delete();
        });

        return response()->json([
            'status' => true,
            'message' => 'Wedding deleted successfully.',
            'data' => [],
        ]);
    }
}
