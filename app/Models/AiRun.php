<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class AiRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'run_type',
        'status',
        'result_summary',
    ];

    protected $casts = [
        'is_executed' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function aiRecommendations(): HasMany
    {
        return $this->hasMany(AiRecommendation::class);
    }

    public function aiRecommendationActions(): HasManyThrough
    {
        return $this->hasManyThrough(
            AiRecommendationAction::class,
            AiRecommendation::class,
        );
    }
}

