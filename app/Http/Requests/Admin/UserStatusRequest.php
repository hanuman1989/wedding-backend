<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UserStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['status' => ['required', 'boolean']];
    }

    protected function prepareForValidation(): void
    {
        $status = $this->input('status');

        if (in_array($status, [true, false, 1, 0, '1', '0', 'true', 'false'], true)) {
            $this->merge(['status' => filter_var($status, FILTER_VALIDATE_BOOLEAN)]);
        }
    }
}
