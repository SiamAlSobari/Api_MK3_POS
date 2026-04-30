<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BusyHourHourlyPrediction extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'daily_forecast_id',
        'hour',
        'predicted_transactions',
        'predicted_revenue',
        'busy_level',
        'emoji',
    ];

    protected $casts = [
        'predicted_transactions' => 'decimal:2',
        'predicted_revenue' => 'decimal:2',
    ];

    public function dailyForecast(): BelongsTo
    {
        return $this->belongsTo(BusyHourDailyForecast::class, 'daily_forecast_id');
    }

    public function productPredictions(): HasMany
    {
        return $this->hasMany(BusyHourProductPrediction::class, 'hourly_prediction_id');
    }
}
