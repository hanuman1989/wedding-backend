<?php

namespace App\Http\Requests;

use App\Models\Wedding;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWeddingRequest extends FormRequest
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
        $required = $this->isMethod('put') ? 'required' : 'sometimes';

        return [
            'creator_type' => [
                $required,
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
                $required,
                'string',
                'max:100',
            ],
            'last_name' => [
                $required,
                'string',
                'max:100',
            ],
            'phone' => [
                $required,
                'string',
                'regex:/^\+[1-9][0-9]{1,14}$/',
            ],
            'email' => [
                $required,
                'string',
                'email',
                'max:255',
            ],
        ];
    }
}
