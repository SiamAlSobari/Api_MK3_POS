<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionItem extends Model
{
    protected $fillable = [
        'transaction_id', 'product_id', 
        'quantity', 'unit_price', 'line_price'
    ];

    // Relasi: banyak item milik 1 transaksi
    public function transaction() {
        return $this->belongsTo(Transaction::class);
    }

    // Relasi: item terhubung ke 1 produk
    public function product() {
        return $this->belongsTo(Product::class);
    }
}