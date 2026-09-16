<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'booking_id',
        'provider',
        'payment_intent_id',
        'client_secret',
        'status',
        'amount',
        'currency',
        'response',
        'paid_at',
    ];

    protected $casts = [
        'response' => 'array',
        'paid_at' => 'datetime',
    ];

    public function booking()
    {
        return $this->belongsTo(
            WeddingBooking::class,
            'booking_id'
        );
    }
}
