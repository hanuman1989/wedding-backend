<?php

namespace App\Models;

use Database\Factories\WeddingCreatorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeddingCreator extends Model
{
    /** @use HasFactory<WeddingCreatorFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'creator_type',
        'first_name',
        'last_name',
        'email',
        'phone',
        'fathers_name',
        'mothers_name',
    ];

    public function wedding(): BelongsTo
    {
        return $this->belongsTo(Wedding::class);
    }
}
