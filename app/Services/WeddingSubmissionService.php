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

        foreach ([
            'first_name' => 'Wedding first name is required.',
            'last_name' => 'Wedding last name is required.',
            'email' => 'Wedding email is required.',
            'phone' => 'Wedding phone is required.',
        ] as $attribute => $message) {
            if (blank($wedding->{$attribute})) {
                $errors[$attribute] = $message;
            }
        }

        $requiredPartnerTypes = match ($wedding->creator_type) {
            'bride' => ['groom'],
            'groom' => ['bride'],
            'other' => ['bride', 'groom'],
        };

        foreach ($requiredPartnerTypes as $creatorType) {
            if (! $creators->has($creatorType)) {
                $errors[$creatorType] = ucfirst($creatorType).' creator details are required.';
            }
        }

        foreach ($creators as $creatorType => $creator) {
            foreach (['first_name', 'last_name', 'email', 'phone'] as $attribute) {
                if (blank($creator->{$attribute})) {
                    $errors["{$creatorType}.{$attribute}"] = ucfirst(str_replace('_', ' ', $attribute))
                        .' is required.';
                }
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

        if ($wedding->number_of_days !== null && $wedding->days->count() !== $wedding->number_of_days) {
            $errors['days'] = 'Wedding day details are incomplete.';
        }

        foreach ($wedding->days as $dayIndex => $day) {
            if (blank($day->wedding_day_date)) {
                $errors["wedding_days.{$dayIndex}.wedding_day_date"] = 'The wedding day date is required.';
            }

            if (blank($day->city)) {
                $errors["wedding_days.{$dayIndex}.city"] = 'The wedding day city is required.';
            }

            if ($day->events->isEmpty()) {
                $errors["wedding_days.{$dayIndex}.wedding_day_events"] = 'Each wedding day requires at least one event.';
            }

            foreach ($day->events as $eventIndex => $event) {
                if (blank($event->title)) {
                    $errors["wedding_days.{$dayIndex}.wedding_day_events.{$eventIndex}.title"] = 'The event title is required.';
                }
            }
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
