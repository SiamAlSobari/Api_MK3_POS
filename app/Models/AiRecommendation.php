<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AiRecommendation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        "ai_run_id",
        "product_id",
        "current_stock",
        "recommed_restok_qty",
        "risk_level",
        "days_until_emty",
        "estimated_emty_date",
        "risk",
        "description",
        "risk_point",
    ];

    protected $casts = [
        "estimated_emty_date" => "date",
    ];

    public function aiRun(): BelongsTo
    {
        return $this->belongsTo(AiRun::class, 'ai_run_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function aiRecommendationActions(): HasMany
    {
        return $this->hasMany(AiRecommendationAction::class, 'ai_recommendation_id');
    }
}