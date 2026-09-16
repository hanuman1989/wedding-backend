<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WeddingBookingDay extends Model
{
    protected $fillable = [
        'booking_id',
        'wedding_day_id',
    ];

    public function booking()
    {
        return $this->belongsTo(
            WeddingBooking::class
        );
    }

    public function weddingDay()
    {
        return $this->belongsTo(
            WeddingDay::class
        );
    }
}
