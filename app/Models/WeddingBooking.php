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

    public const STATUS_PENDING = 'pending_payment';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_FAILED = 'payment_failed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_COMPLETED = 'completed';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_CONFIRMED,
        self::STATUS_CANCELLED,
        self::STATUS_EXPIRED,
        self::STATUS_COMPLETED,
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
