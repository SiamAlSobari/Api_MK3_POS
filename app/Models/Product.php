<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        "name",
        "price",
        "description",
        "stock",
        "image_url",
        "category_id",
    ];

    protected $casts = [
        "price" => "decimal:2",
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
