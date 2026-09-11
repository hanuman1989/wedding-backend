<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReorderWeddingImagesRequest;
use App\Http\Requests\UploadWeddingImagesRequest;
use App\Http\Resources\WeddingImageResource;
use App\Models\Wedding;
use App\Models\WeddingImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class WeddingImageController extends Controller
{
    public function store(UploadWeddingImagesRequest $request, Wedding $wedding): JsonResponse
    {
        $storedPaths = [];
        try {
            $images = DB::transaction(function () use ($request, $wedding, &$storedPaths) {
                $nextSortOrder = ((int) ($wedding->images()->max('sort_order') ?? -1)) + 1;
                $images = collect();

                foreach ($request->file('images') as $uploadedImage) {
                    $path = $uploadedImage->store("weddings/{$wedding->id}", 'public');

                    if ($path === false) {
                        throw new RuntimeException('Unable to store the wedding image.');
                    }

                    $storedPaths[] = $path;
                    $images->push($wedding->images()->create([
                        'image' => $path,
                        'disk' => 'public',
                        'original_name' => $uploadedImage->getClientOriginalName(),
                        'mime_type' => $uploadedImage->getMimeType(),
                        'file_size' => $uploadedImage->getSize(),
                        'sort_order' => $nextSortOrder++,
                    ]));
                }

                $wedding->forceFill([
                    'current_step' => max($wedding->current_step, 5),
                ])->save();

                return $images;
            });
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($storedPaths);

            throw $exception;
        }

        return response()->json([
            'status' => true,
            'message' => 'Wedding images uploaded successfully.',
            'data' => WeddingImageResource::collection($images)->resolve(),
        ]);
    }

    public function destroy(Wedding $wedding, WeddingImage $image): JsonResponse
    {
        abort_unless($image->wedding_id === $wedding->id, 404);

        if (! Storage::disk($image->disk)->delete($image->image)) {
            throw new RuntimeException('Unable to delete the wedding image.');
        }

        DB::transaction(function () use ($wedding, $image): void {
            $image->delete();
            $wedding->images()->get()->each(
                fn (WeddingImage $remainingImage, int $sortOrder) => $remainingImage->update([
                    'sort_order' => $sortOrder,
                ]),
            );
        });

        return response()->json([
            'status' => true,
            'message' => 'Wedding image deleted successfully.',
            'data' => [],
        ]);
    }

    public function reorder(ReorderWeddingImagesRequest $request, Wedding $wedding): JsonResponse
    {
        $images = DB::transaction(function () use ($request, $wedding) {
            $validated = $request->validated();
            $imageIds = $validated['image_ids'];

            if (count($imageIds) !== $wedding->images()->count()) {
                throw ValidationException::withMessages([
                    'image_ids' => 'Provide every wedding image when reordering.',
                ]);
            }

            $images = $wedding->images()->whereKey($imageIds)->get();

            abort_unless($images->count() === count($imageIds), 404);

            foreach ($imageIds as $sortOrder => $imageId) {
                $wedding->images()
                    ->whereKey($imageId)
                    ->update(['sort_order' => $sortOrder]);
            }

            return $wedding->images()->get();
        });

        return response()->json([
            'status' => true,
            'message' => 'Wedding images reordered successfully.',
            'data' => WeddingImageResource::collection($images)->resolve(),
        ]);
    }
}
