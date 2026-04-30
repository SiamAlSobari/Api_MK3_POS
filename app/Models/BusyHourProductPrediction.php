<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BusyHourProductPrediction extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'hourly_prediction_id',
        'product_id',
        'product_name',
        'probability',
        'estimated_qty',
        'estimated_revenue',
    ];

    protected $casts = [
        'probability' => 'decimal:3',
        'estimated_qty' => 'decimal:1',
        'estimated_revenue' => 'decimal:2',
    ];

    public function hourlyPrediction(): BelongsTo
    {
        return $this->belongsTo(BusyHourHourlyPrediction::class, 'hourly_prediction_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
