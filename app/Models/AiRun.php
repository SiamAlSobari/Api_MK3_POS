<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AiRun extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        "user_id",
        "type_ai",
        "status",
        "generated_at",
        "error_message",
        "seasonal_insight",
        "total_products",
    ];

    protected $casts = [
        "generated_at" => "datetime",
        "seasonal_insight" => "array",
    ];

    public function aiRecommendations(): HasMany
    {
        return $this->hasMany(AiRecommendation::class, 'ai_run_id');
    }

    public function busyHourDailyForecasts(): HasMany
    {
        return $this->hasMany(BusyHourDailyForecast::class, 'ai_run_id');
    }

    public function portfolioInsight(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(AiPortfolioInsight::class, 'ai_run_id');
    }
}