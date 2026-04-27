<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiRecommendationAction extends Model
{
    use HasFactory;

    protected $fillable = [
        'ai_recommendation_id',
        'action_type',
        'action_data',
        'is_executed',
        'executed_at',
    ];

    protected $casts = [
        'action_data' => 'array',
        'is_executed' => 'boolean',
        'executed_at' => 'datetime',
    ];

    public function aiRecommendation(): BelongsTo
    {
        return $this->belongsTo(AiRecommendation::class);
    }
}

