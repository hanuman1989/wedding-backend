<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class AdminUserRequest extends FormRequest
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
    public function rules()
    {
        $userType = $this->input('user_type');
        $isUpdate = $this->isMethod('put') || $this->isMethod('patch');
        $rules = [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:admin_users,email'.($isUpdate ? ','.$this->route('admin_user.id') : ''),
            'mobile' => 'required|unique:admin_users,mobile'.($isUpdate ? ','.$this->route('admin_user.id') : ''),
            // 'password' => 'required|string|min:8|confirmed',
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                Password::defaults(),
            ],
        ];

        return $rules;
    }
}
