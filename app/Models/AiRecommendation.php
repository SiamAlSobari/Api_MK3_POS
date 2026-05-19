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
        "product_name",
        "product_price",
        "current_stock",
        "avg_daily_sales",
        "recommed_restok_qty",
        "restock_min",
        "restock_max",
        "restock_label",
        "target_days_coverage",
        "risk_level",
        "urgency_description",
        "days_until_emty",
        "estimated_emty_date",
        "risk",
        "description",
        "risk_point",
        "stock_timeline",
    ];

    protected $casts = [
        "estimated_emty_date" => "date",
        "product_price" => "decimal:2",
        "avg_daily_sales" => "decimal:2",
        "stock_timeline" => "array",
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