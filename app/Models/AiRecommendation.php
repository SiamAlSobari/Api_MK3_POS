<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiRecommendation extends Model
{
    use HasFactory;

    protected $fillable = [
        'ai_run_id',
        'title',
        'description',
        'priority',
        'category',
    ];

    public function aiRun(): BelongsTo
    {
        return $this->belongsTo(AiRun::class);
    }

    public function aiRecommendationActions(): HasMany
    {
        return $this->hasMany(AiRecommendationAction::class);
    }
}

