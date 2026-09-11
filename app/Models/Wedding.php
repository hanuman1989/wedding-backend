<?php

namespace App\Models;

use Database\Factories\WeddingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Wedding extends Model
{
    /** @use HasFactory<WeddingFactory> */
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_ENDED = 'ended';
    public const STATUS_CANCELLED = 'cancelled';
    /** @var list<string> */
    public const CREATOR_TYPES = [
        'bride',
        'groom',
        'other',
    ];

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_SUBMITTED,
        self::STATUS_PUBLISHED,
        self::STATUS_ENDED,
        self::STATUS_CANCELLED,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'creator_type',
        'creator_type_other',
        'description',
        'video_url',
        'number_of_days',
        'food_observance',
        'is_alcohol_offered',
        'main_languages',
        'status',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_alcohol_offered' => 'boolean',
            'main_languages' => 'array',
            'submitted_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creators(): HasMany
    {
        return $this->hasMany(WeddingCreator::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(WeddingImage::class)->orderBy('sort_order');
    }

    public function days(): HasMany
    {
        return $this->hasMany(WeddingDay::class)->orderBy('id');
    }

    /**
     * Get the primary/first wedding image.
     */
    public function thumbnail(): HasOne
    {
        return $this->hasOne(WeddingImage::class)
            ->ofMany('sort_order', 'min');
    }
}
