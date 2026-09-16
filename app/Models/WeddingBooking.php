<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WeddingBooking extends Model
{
    protected $fillable = [
        'wedding_id',
        'user_id',
        'booking_number',
        'status',
        'first_name',
        'last_name',
        'email',
        'phone',
        'visiting_from',
        'heard_about',
        'number_of_travelers',
        'price_per_person',
        'subtotal',
        'platform_fee',
        'payment_fee',
        'total_amount',
        'currency',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function wedding()
    {
        return $this->belongsTo(Wedding::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function days()
    {
        return $this->hasMany(WeddingBookingDay::class, 'booking_id');
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }
}
