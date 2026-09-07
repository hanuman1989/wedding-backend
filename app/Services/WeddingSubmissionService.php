<?php

namespace App\Services;

use App\Models\Wedding;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WeddingSubmissionService
{
    public function submit(Wedding $wedding): Wedding
    {
        $wedding->load(['creators', 'days.events']);

        $errors = $this->completionErrors($wedding);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return DB::transaction(function () use ($wedding): Wedding {
            $wedding->forceFill([
                'status' => Wedding::STATUS_PUBLISHED,
                'submitted_at' => now(),
                'published_at' => now(),
            ])->save();

            return $wedding;
        });
    }

    /**
     * @return array<string, string>
     */
    private function completionErrors(Wedding $wedding): array
    {
        $errors = [];
        $creators = $wedding->creators->keyBy('creator_type');

        if (! $creators->has($wedding->creator_type)) {
            $errors['creator'] = 'Step 1 creator details are required.';
        }

        $requiredPartnerTypes = match ($wedding->creator_type) {
            'bride' => ['groom'],
            'groom' => ['bride'],
            'other' => ['bride', 'groom'],
        };

        foreach ($requiredPartnerTypes as $creatorType) {
            if (! $creators->has($creatorType)) {
                $errors[$creatorType] = ucfirst($creatorType).' details are required.';
            }
        }

        if (blank($wedding->description)) {
            $errors['description'] = 'Wedding story details are required.';
        }

        if ($wedding->number_of_days === null) {
            $errors['number_of_days'] = 'Wedding day details are required.';
        }

        if (blank($wedding->food_observance)) {
            $errors['food_observance'] = 'Food observance is required.';
        }

        if ($wedding->is_alcohol_offered === null) {
            $errors['is_alcohol_offered'] = 'Alcohol availability is required.';
        }

        if (! is_array($wedding->main_languages) || $wedding->main_languages === []) {
            $errors['main_languages'] = 'At least one main language is required.';
        }

        if ($wedding->number_of_days !== null && $wedding->days->count() !== $wedding->number_of_days) {
            $errors['days'] = 'Wedding day details are incomplete.';
        }

        if ($wedding->days->contains(fn ($day) => $day->events->isEmpty())) {
            $errors['events'] = 'Each wedding day requires at least one event.';
        }

        if (! $wedding->images()->exists()) {
            $errors['images'] = 'At least one wedding image is required.';
        }

        if ($wedding->status !== Wedding::STATUS_DRAFT) {
            $errors['status'] = 'Only a draft wedding can be submitted.';
        }

        return $errors;
    }
}
