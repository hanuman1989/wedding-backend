<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class WeddingIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'keyword' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'start_date' => [
                'sometimes',
                'nullable',
                'date',
            ],

            'end_date' => [
                'sometimes',
                'nullable',
                'date',
                'after_or_equal:start_date',
            ],

            'status' => [
                'sometimes',
                'nullable',
                'string',
                'max:20',
            ],

            'latitude' => [
                'sometimes',
                'nullable',
                'numeric',
                'between:-90,90',
            ],

            'longitude' => [
                'sometimes',
                'nullable',
                'numeric',
                'between:-180,180',
            ],

            'user_id' => [
                'sometimes',
                'nullable',
                'integer',
                'exists:users,id',
            ],

            'per_page' => [
                'sometimes',
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
            'limit' => [
                'sometimes',
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
        ];
    }
}
