<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['user_type', 'status'] as $filter) {
            $value = $this->input($filter);

            if (in_array($value, [true, false, 1, 0, '1', '0', 'true', 'false'], true)) {
                $this->merge([$filter => filter_var($value, FILTER_VALIDATE_BOOLEAN)]);
            }
        }
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $rules = [
            'keyword' => ['sometimes', 'nullable', 'string', 'max:255'],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'end_date' => ['sometimes', 'nullable', 'date', Rule::when($this->filled('start_date'), 'after_or_equal:start_date')],
            'user_type' => ['sometimes', 'nullable', 'boolean'],
            'status' => ['sometimes', 'nullable', 'boolean'],
        ];

        if ($this->routeIs('admin.users.index')) {
            $rules['per_page'] = ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'];
            $rules['page'] = ['sometimes', 'nullable', 'integer', 'min:1'];
        }

        return $rules;
    }
}
