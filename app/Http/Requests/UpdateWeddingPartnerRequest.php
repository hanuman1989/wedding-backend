<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Models\Wedding;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateWeddingPartnerRequest extends FormRequest
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
        $wedding = $this->route('wedding');
        $requiresBride = $wedding instanceof Wedding
            && in_array($wedding->creator_type, ['groom', 'other'], true);
        $requiresGroom = $wedding instanceof Wedding
            && in_array($wedding->creator_type, ['bride', 'other'], true);

        return [
            ...$this->creatorRules('bride', $requiresBride),
            ...$this->creatorRules('groom', $requiresGroom),
        ];
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    private function creatorRules(string $prefix, bool $required): array
    {
        $presenceRule = $required ? 'required' : 'prohibited';

        return [
            $prefix => [$presenceRule, 'array'],
            "{$prefix}.first_name" => [$presenceRule, 'string', 'max:100'],
            "{$prefix}.last_name" => [$presenceRule, 'string', 'max:100'],
            "{$prefix}.email" => [$presenceRule, 'email', 'max:255'],
            "{$prefix}.phone" => [$presenceRule, 'string', 'regex:/^\+[1-9][0-9]{1,14}$/'],
            "{$prefix}.fathers_name" => ['nullable', 'string', 'max:100'],
            "{$prefix}.mothers_name" => ['nullable', 'string', 'max:100'],
        ];
    }
}
