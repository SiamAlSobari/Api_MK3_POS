<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AiSeasonalRecommendation extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'ai_seasonal_recommendations';

    protected $fillable = [
        'ai_recommendation_id',
        'min',
        'max',
        'label',
        'holiday',
        'reason',
    ];

    public function aiRecommendation(): BelongsTo
    {
        return $this->belongsTo(AiRecommendation::class, 'ai_recommendation_id');
    }
}
