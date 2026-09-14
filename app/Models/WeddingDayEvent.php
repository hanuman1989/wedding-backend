<?php

namespace App\Models;

use Database\Factories\WeddingDayEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeddingDayEvent extends Model
{
    /** @use HasFactory<WeddingDayEventFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'wedding_day_id',
        'title',
        'description',
        'start_time',
        'end_time',
        'is_music_or_dancing',
        'dress_code',
        'sort_order',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_music_or_dancing' => 'boolean',
            'wedding_day_id' => 'integer',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'sort_order' => 'integer',
        ];
    }

    public function weddingDay(): BelongsTo
    {
        return $this->belongsTo(WeddingDay::class);
    }
}
