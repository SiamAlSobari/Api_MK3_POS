<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Stock extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = ["product_id", "stock_on_hand"];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
