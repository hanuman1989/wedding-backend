<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateWeddingDetailsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'number_of_days' => [
                'required',
                'integer',
                'between:1,10',
            ],
            'food_observance' => [
                'required',
                'string',
                'max:100',
            ],
            'is_alcohol_offered' => [
                'required',
                'boolean',
            ],
            'main_languages' => [
                'required',
                'array',
                'min:1',
                'max:10',
            ],
            'main_languages.*' => [
                'required',
                'string',
                'max:50',
            ],
            'days' => [
                'required',
                'array',
                'min:1',
                'max:10',
            ],
            'days.*.id' => [
                'nullable',
                'integer',
                'distinct',
            ],
            'days.*.day_number' => [
                'required',
                'integer',
                'between:1,10',
                'distinct',
            ],
            'days.*.wedding_day_date' => [
                'required',
                'date_format:Y-m-d',
            ],
            'days.*.address_line_1' => [
                'nullable',
                'string',
                'max:255',
            ],
            'days.*.address_line_2' => [
                'nullable',
                'string',
                'max:255',
            ],
            'days.*.city' => [
                'required',
                'string',
                'max:100',
            ],
            'days.*.state' => [
                'nullable',
                'string',
                'max:100',
            ],
            'days.*.country' => [
                'nullable',
                'string',
                'max:100',
            ],
            'days.*.post_code' => [
                'nullable',
                'string',
                'max:20',
            ],
            'days.*.landmark_near' => [
                'nullable',
                'string',
                'max:255',
            ],
            'days.*.latitude' => [
                'nullable',
                'numeric',
                'between:-90,90',
            ],
            'days.*.longitude' => [
                'nullable',
                'numeric',
                'between:-180,180',
            ],
            'days.*.events' => [
                'required',
                'array',
                'min:1',
            ],
            'days.*.events.*.id' => [
                'nullable',
                'integer',
                'distinct',
            ],
            'days.*.events.*.title' => [
                'required',
                'string',
                'max:255',
            ],
            'days.*.events.*.description' => [
                'nullable',
                'string',
                'max:2000',
            ],
            'days.*.events.*.start_time' => [
                'nullable',
                'date_format:H:i',
            ],
            'days.*.events.*.end_time' => [
                'nullable',
                'date_format:H:i',
            ],
            'days.*.events.*.is_music_or_dancing' => [
                'required',
                'boolean',
            ],
            'days.*.events.*.dress_code' => [
                'nullable',
                'string',
                'max:100',
            ],
            'days.*.events.*.venue_name' => [
                'nullable',
                'string',
                'max:255',
            ],
            'days.*.events.*.address_line_1' => [
                'nullable',
                'string',
                'max:255',
            ],
            'days.*.events.*.address_line_2' => [
                'nullable',
                'string',
                'max:255',
            ],
            'days.*.events.*.city' => [
                'nullable',
                'string',
                'max:100',
            ],
            'days.*.events.*.state' => [
                'nullable',
                'string',
                'max:100',
            ],
            'days.*.events.*.country' => [
                'nullable',
                'string',
                'max:100',
            ],
            'days.*.events.*.post_code' => [
                'nullable',
                'string',
                'max:20',
            ],
            'days.*.events.*.latitude' => [
                'nullable',
                'numeric',
                'between:-90,90',
            ],
            'days.*.events.*.longitude' => [
                'nullable',
                'numeric',
                'between:-180,180',
            ],
            'days.*.events.*.sort_order' => [
                'nullable',
                'integer',
                'min:0',
            ],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->any()) {
                return;
            }

            $days = $this->array('days');

            if (count($days) !== $this->integer('number_of_days')) {
                $validator->errors()->add('days', 'The number of days must match number_of_days.');
            }

            foreach ($days as $dayIndex => $day) {
                foreach ($day['events'] as $eventIndex => $event) {
                    $startTime = $event['start_time'] ?? null;
                    $endTime = $event['end_time'] ?? null;

                    if (is_string($startTime) && is_string($endTime) && $endTime <= $startTime) {
                        $validator->errors()->add(
                            "days.{$dayIndex}.events.{$eventIndex}.end_time",
                            'The end time must be after the start time.',
                        );
                    }
                }
            }
        }];
    }
}
