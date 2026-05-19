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
        'est_trx_min',
        'est_trx_max',
        'est_trx_label',
        'predicted_revenue',
        'est_revenue_min',
        'est_revenue_max',
        'est_revenue_label',
        'busy_level',
        'busy_label',
        'emoji',
        'what_to_prepare',
    ];

    protected $casts = [
        'predicted_transactions' => 'decimal:2',
        'predicted_revenue' => 'decimal:2',
        'est_revenue_min' => 'decimal:2',
        'est_revenue_max' => 'decimal:2',
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
