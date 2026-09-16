<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreWeddingBookingRequest extends FormRequest
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
            'first_name' => [
                'required',
                'string',
                'max:100',
            ],

            'last_name' => [
                'required',
                'string',
                'max:100',
            ],

            'email' => [
                'required',
                'email',
                'max:255',
            ],

            'phone' => [
                'required',
                'string',
                'max:30',
            ],

            'visiting_from' => [
                'nullable',
                'string',
                'max:255',
            ],

            'heard_about' => [
                'nullable',
                'string',
                'max:100',
            ],

            'number_of_travelers' => [
                'required',
                'integer',
                'min:1',
            ],

            'selected_days' => [
                'required',
                'array',
                'min:1',
            ],

            'selected_days.*' => [
                'required',
                'integer',
                'distinct',
            ],
        ];
    }

     /**
     * Get custom messages for validation errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'first_name.required' =>
                'First name is required.',

            'last_name.required' =>
                'Last name is required.',

            'email.required' =>
                'Email address is required.',

            'email.email' =>
                'Please enter a valid email address.',

            'phone.required' =>
                'Phone number is required.',

            'number_of_travelers.required' =>
                'Please select the number of travelers.',

            'number_of_travelers.min' =>
                'At least one traveler is required.',

            'selected_days.required' =>
                'Please select at least one wedding day.',

            'selected_days.min' =>
                'Please select at least one wedding day.',

            'selected_days.*.distinct' =>
                'A wedding day cannot be selected more than once.',
        ];
    }
}
