<?php

namespace App\Models;

use Database\Factories\WeddingDayFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WeddingDay extends Model
{
    /** @use HasFactory<WeddingDayFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'day_number',
        'wedding_day_date',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'country',
        'post_code',
        'landmark_near',
        'latitude',
        'longitude',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'wedding_day_date' => 'date',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function wedding(): BelongsTo
    {
        return $this->belongsTo(Wedding::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(WeddingDayEvent::class)->orderBy('sort_order');
    }
}
