<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BusyHourDailyForecast extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'ai_run_id',
        'forecast_date',
        'day_name',
        'day_of_week',
        'is_weekend',
        'total_predicted_trx',
        'est_trx_min',
        'est_trx_max',
        'est_trx_label',
        'total_predicted_revenue',
        'est_revenue_min',
        'est_revenue_max',
        'est_revenue_label',
        'peak_hour',
        'peak_hour_label',
        'peak_hour_trx',
        'busy_hours_count',
    ];

    protected $casts = [
        'forecast_date' => 'date',
        'is_weekend' => 'boolean',
        'total_predicted_trx' => 'decimal:2',
        'total_predicted_revenue' => 'decimal:2',
        'peak_hour_trx' => 'decimal:2',
        'est_revenue_min' => 'decimal:2',
        'est_revenue_max' => 'decimal:2',
    ];

    public function aiRun(): BelongsTo
    {
        return $this->belongsTo(AiRun::class, 'ai_run_id');
    }

    public function hourlyPredictions(): HasMany
    {
        return $this->hasMany(BusyHourHourlyPrediction::class, 'daily_forecast_id');
    }
}
