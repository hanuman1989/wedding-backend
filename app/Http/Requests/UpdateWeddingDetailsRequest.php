<?php

namespace App\Http\Requests;

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
        return $this->user() !== null;
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
                'nullable',
                'boolean',
            ],
            'wedding_days' => [
                'required',
                'array',
                'min:1',
                'max:10',
            ],
            'wedding_days.*.id' => [
                'nullable',
                'integer',
                'distinct',
            ],
            'wedding_days.*.wedding_day_date' => [
                'required',
                'date_format:Y-m-d',
            ],
            'wedding_days.*.wedding_day_time' => [
                'nullable',
                'date_format:H:i',
            ],
            'wedding_days.*.address_line_1' => [
                'nullable',
                'string',
                'max:255',
            ],
            'wedding_days.*.address_line_2' => [
                'nullable',
                'string',
                'max:255',
            ],
            'wedding_days.*.city' => [
                'required',
                'string',
                'max:100',
            ],
            'wedding_days.*.state' => [
                'nullable',
                'string',
                'max:100',
            ],
            'wedding_days.*.country' => [
                'nullable',
                'string',
                'max:100',
            ],
            'wedding_days.*.post_code' => [
                'nullable',
                'string',
                'max:20',
            ],
            'wedding_days.*.landmark_near' => [
                'nullable',
                'string',
                'max:255',
            ],
            'wedding_days.*.latitude' => [
                'nullable',
                'numeric',
                'between:-90,90',
            ],
            'wedding_days.*.longitude' => [
                'nullable',
                'numeric',
                'between:-180,180',
            ],
            'wedding_days.*.wedding_day_events' => [
                'required',
                'array',
            ],
            'wedding_days.*.wedding_day_events.*.id' => [
                'nullable',
                'integer',
                'distinct',
            ],
            'wedding_days.*.wedding_day_events.*.title' => [
                'required',
                'string',
                'max:255',
            ],
            'wedding_days.*.wedding_day_events.*.description' => [
                'required',
                'string',
                'max:2000',
            ],
            'wedding_days.*.wedding_day_events.*.start_time' => [
                'nullable',
                'date_format:H:i',
            ],
            'wedding_days.*.wedding_day_events.*.is_music_or_dancing' => [
                'nullable',
                'boolean',
            ],
            'wedding_days.*.wedding_day_events.*.dress_code' => [
                'required',
                'string',
                'max:100',
            ],
            'wedding_days.*.wedding_day_events.*.venue_name' => [
                'nullable',
                'string',
                'max:255',
            ],
            'wedding_days.*.wedding_day_events.*.address_line_1' => [
                'nullable',
                'string',
                'max:255',
            ],
            'wedding_days.*.wedding_day_events.*.address_line_2' => [
                'nullable',
                'string',
                'max:255',
            ],
            'wedding_days.*.wedding_day_events.*.city' => [
                'nullable',
                'string',
                'max:100',
            ],
            'wedding_days.*.wedding_day_events.*.state' => [
                'nullable',
                'string',
                'max:100',
            ],
            'wedding_days.*.wedding_day_events.*.country' => [
                'nullable',
                'string',
                'max:100',
            ],
            'wedding_days.*.wedding_day_events.*.post_code' => [
                'nullable',
                'string',
                'max:20',
            ],
            'wedding_days.*.wedding_day_events.*.latitude' => [
                'nullable',
                'numeric',
                'between:-90,90',
            ],
            'wedding_days.*.wedding_day_events.*.longitude' => [
                'nullable',
                'numeric',
                'between:-180,180',
            ],
            'wedding_days.*.wedding_day_events.*.sort_order' => [
                'nullable',
                'integer',
                'min:0',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'number_of_days.required' => 'Please provide the number of wedding days.',
            'number_of_days.integer' => 'The number of wedding days must be a whole number.',
            'number_of_days.between' => 'The number of wedding days must be between 1 and 10.',
            'food_observance.required' => 'Please provide the food observance.',
            'food_observance.string' => 'The food observance must be text.',
            'food_observance.max' => 'The food observance may not exceed 100 characters.',
            'is_alcohol_offered.boolean' => 'The alcohol offered value must be true or false.',
            'wedding_days.required' => 'Please provide the wedding days.',
            'wedding_days.array' => 'The wedding days must be provided as a list.',
            'wedding_days.min' => 'Please provide at least one wedding day.',
            'wedding_days.max' => 'You may provide a maximum of 10 wedding days.',
            'wedding_days.*.id.integer' => 'Each wedding day ID must be a whole number.',
            'wedding_days.*.id.distinct' => 'Each wedding day ID must be unique.',
            'wedding_days.*.wedding_day_date.required' => 'Please provide the date for wedding day :position.',
            'wedding_days.*.wedding_day_date.date_format' => 'The wedding day date must use the format YYYY-MM-DD.',
            'wedding_days.*.wedding_day_time.date_format' => 'The wedding day time must use the format HH:MM.',
            'wedding_days.*.address_line_1.string' => 'The first address line must be text.',
            'wedding_days.*.address_line_1.max' => 'The first address line may not exceed 255 characters.',
            'wedding_days.*.address_line_2.string' => 'The second address line must be text.',
            'wedding_days.*.address_line_2.max' => 'The second address line may not exceed 255 characters.',
            'wedding_days.*.city.required' => 'Please provide the city for wedding day :position.',
            'wedding_days.*.city.string' => 'The city must be text.',
            'wedding_days.*.city.max' => 'The city may not exceed 100 characters.',
            'wedding_days.*.state.string' => 'The state must be text.',
            'wedding_days.*.state.max' => 'The state may not exceed 100 characters.',
            'wedding_days.*.country.string' => 'The country must be text.',
            'wedding_days.*.country.max' => 'The country may not exceed 100 characters.',
            'wedding_days.*.post_code.string' => 'The post code must be text.',
            'wedding_days.*.post_code.max' => 'The post code may not exceed 20 characters.',
            'wedding_days.*.landmark_near.string' => 'The nearby landmark must be text.',
            'wedding_days.*.landmark_near.max' => 'The nearby landmark may not exceed 255 characters.',
            'wedding_days.*.latitude.numeric' => 'The latitude must be a number.',
            'wedding_days.*.latitude.between' => 'The latitude must be between -90 and 90.',
            'wedding_days.*.longitude.numeric' => 'The longitude must be a number.',
            'wedding_days.*.longitude.between' => 'The longitude must be between -180 and 180.',
            'wedding_days.*.wedding_day_events.required' => 'Please provide the events for wedding day :position.',
            'wedding_days.*.wedding_day_events.array' => 'The wedding day events must be provided as a list.',
            'wedding_days.*.wedding_day_events.*.id.integer' => 'Each wedding event ID must be a whole number.',
            'wedding_days.*.wedding_day_events.*.id.distinct' => 'Each wedding event ID must be unique.',
            'wedding_days.*.wedding_day_events.*.title.required' => 'Please provide the title for event :position.',
            'wedding_days.*.wedding_day_events.*.title.string' => 'The event title must be text.',
            'wedding_days.*.wedding_day_events.*.title.max' => 'The event title may not exceed 255 characters.',
            'wedding_days.*.wedding_day_events.*.description.required' => 'Please provide the description for event :position.',
            'wedding_days.*.wedding_day_events.*.description.string' => 'The event description must be text.',
            'wedding_days.*.wedding_day_events.*.description.max' => 'The event description may not exceed 2,000 characters.',
            'wedding_days.*.wedding_day_events.*.start_time.date_format' => 'The event start time must use the format HH:MM.',
            'wedding_days.*.wedding_day_events.*.is_music_or_dancing.boolean' => 'The music or dancing value must be true or false.',
            'wedding_days.*.wedding_day_events.*.dress_code.required' => 'Please provide the dress code for event :position.',
            'wedding_days.*.wedding_day_events.*.dress_code.string' => 'The dress code must be text.',
            'wedding_days.*.wedding_day_events.*.dress_code.max' => 'The dress code may not exceed 100 characters.',
            'wedding_days.*.wedding_day_events.*.venue_name.string' => 'The venue name must be text.',
            'wedding_days.*.wedding_day_events.*.venue_name.max' => 'The venue name may not exceed 255 characters.',
            'wedding_days.*.wedding_day_events.*.address_line_1.string' => 'The event first address line must be text.',
            'wedding_days.*.wedding_day_events.*.address_line_1.max' => 'The event first address line may not exceed 255 characters.',
            'wedding_days.*.wedding_day_events.*.address_line_2.string' => 'The event second address line must be text.',
            'wedding_days.*.wedding_day_events.*.address_line_2.max' => 'The event second address line may not exceed 255 characters.',
            'wedding_days.*.wedding_day_events.*.city.string' => 'The event city must be text.',
            'wedding_days.*.wedding_day_events.*.city.max' => 'The event city may not exceed 100 characters.',
            'wedding_days.*.wedding_day_events.*.state.string' => 'The event state must be text.',
            'wedding_days.*.wedding_day_events.*.state.max' => 'The event state may not exceed 100 characters.',
            'wedding_days.*.wedding_day_events.*.country.string' => 'The event country must be text.',
            'wedding_days.*.wedding_day_events.*.country.max' => 'The event country may not exceed 100 characters.',
            'wedding_days.*.wedding_day_events.*.post_code.string' => 'The event post code must be text.',
            'wedding_days.*.wedding_day_events.*.post_code.max' => 'The event post code may not exceed 20 characters.',
            'wedding_days.*.wedding_day_events.*.latitude.numeric' => 'The event latitude must be a number.',
            'wedding_days.*.wedding_day_events.*.latitude.between' => 'The event latitude must be between -90 and 90.',
            'wedding_days.*.wedding_day_events.*.longitude.numeric' => 'The event longitude must be a number.',
            'wedding_days.*.wedding_day_events.*.longitude.between' => 'The event longitude must be between -180 and 180.',
            'wedding_days.*.wedding_day_events.*.sort_order.integer' => 'The event sort order must be a whole number.',
            'wedding_days.*.wedding_day_events.*.sort_order.min' => 'The event sort order cannot be negative.',
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

            $days = $this->array('wedding_days');

            if (count($days) !== $this->integer('number_of_days')) {
                $validator->errors()->add('wedding_days', 'The number of days must match number_of_days.');
            }
        }];
    }
}
