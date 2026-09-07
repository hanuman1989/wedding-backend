<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Models\Wedding;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWeddingRequest extends FormRequest
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
            'creator_type' => [
                'required',
                'string',
                Rule::in(Wedding::CREATOR_TYPES),
            ],
            'creator_type_other' => [
                'nullable',
                'string',
                'max:100',
                'required_if:creator_type,other',
                'exclude_unless:creator_type,other',
            ],
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
                Rule::in([$this->user()?->email]),
            ],
            'phone' => [
                'required',
                'string',
                'regex:/^\+[1-9][0-9]{1,14}$/',
            ],
            'fathers_name' => [
                'nullable',
                'string',
                'max:100',
            ],
            'mothers_name' => [
                'nullable',
                'string',
                'max:100',
            ],
        ];
    }
}
