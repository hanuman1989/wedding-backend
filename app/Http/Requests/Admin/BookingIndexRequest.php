<?php

namespace App\Http\Requests\Admin;

use App\Models\Payment;
use App\Models\WeddingBooking;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BookingIndexRequest extends FormRequest
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
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        $rules = [
            'keyword' => ['sometimes', 'nullable', 'string', 'max:255'],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'end_date' => [
                'sometimes', 'nullable', 'date',
                Rule::when($this->filled('start_date'), 'after_or_equal:start_date'),
            ],
            'booking_status' => [
                'sometimes', 'nullable', 'string',
                Rule::in([...WeddingBooking::STATUSES, WeddingBooking::STATUS_FAILED]),
            ],
            'payment_status' => ['sometimes', 'nullable', 'string', Rule::in(Payment::STATUSES)],
        ];

        if ($this->routeIs('admin.admin.bookings.index')) {
            $rules['per_page'] = ['sometimes', 'nullable', 'integer', 'min:1'];
            $rules['limit'] = ['sometimes', 'nullable', 'integer', 'min:1'];
        }

        return $rules;
    }
}
