<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateWeddingDetailsRequest;
use App\Http\Requests\UpdateWeddingPartnerRequest;
use App\Http\Requests\UpdateWeddingStoryRequest;
use App\Http\Resources\WeddingResource;
use App\Models\Wedding;
use App\Services\WeddingDetailsSynchronizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class WeddingStepController extends Controller
{
    public function updatePartnerDetails(UpdateWeddingPartnerRequest $request, Wedding $wedding): JsonResponse
    {

        $wedding = DB::transaction(function () use ($request, $wedding): Wedding {
            $partnerCreatorTypes = match ($wedding->creator_type) {
                'bride' => ['groom'],
                'groom' => ['bride'],
                'other' => ['bride', 'groom'],
            };
            $validated = $request->validated();

            foreach ($partnerCreatorTypes as $creatorType) {
                $wedding->creators()->updateOrCreate(
                    ['creator_type' => $creatorType],
                    $validated[$creatorType],
                );
            }

            $wedding->forceFill([
                'current_step' => max($wedding->current_step, 2),
            ])->save();

            return $wedding;
        });

        return response()->json([
            'status' => true,
            'message' => 'Partner details saved successfully.',
            'data' => (new WeddingResource($wedding->load('creators', 'images', 'days.events')))->resolve(),
        ]);
    }

    public function updateStory(UpdateWeddingStoryRequest $request, Wedding $wedding): JsonResponse
    {
        $wedding->update($request->safe()->only(['description', 'video_url']));
        $wedding->forceFill([
            'current_step' => max($wedding->current_step, 3),
        ])->save();

        return response()->json([
            'status' => true,
            'message' => 'Wedding story saved successfully.',
            'data' => (new WeddingResource($wedding->load('creators', 'images', 'days.events')))->resolve(),
        ]);
    }

    public function updateWeddingDetails(UpdateWeddingDetailsRequest $request, Wedding $wedding, WeddingDetailsSynchronizer $synchronizer): JsonResponse
    {
        $wedding = $synchronizer->synchronize($wedding, $request->validated());

        return response()->json([
            'status' => true,
            'message' => 'Wedding details saved successfully.',
            'data' => (new WeddingResource($wedding->load('creators', 'images', 'days.events')))->resolve(),
        ]);
    }
}
