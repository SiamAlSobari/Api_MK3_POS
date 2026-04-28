<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AiRecommendationAction extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        "ai_recommendation_id",
        "action_type",
        "action_at",
    ];

    protected $casts = [
        "action_at" => "datetime",
    ];

    public function aiRecommendation(): BelongsTo
    {
        return $this->belongsTo(AiRecommendation::class, 'ai_recommendation_id');
    }
}