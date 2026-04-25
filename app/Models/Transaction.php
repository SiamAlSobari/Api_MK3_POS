<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use SoftDeletes; // Sesuai struktur ada deleted_at [cite: 12]

    protected $fillable = [
        'user_id', 'trx_type', 'trx_date', 
        'payment_method', 'paid_at', 'total_amount'
    ];

    // Relasi: 1 transaksi dimiliki oleh 1 user 
    public function user() {
        return $this->belongsTo(User::class);
    }

    // Relasi: 1 transaksi memiliki banyak item 
    public function items() {
        return $this->hasMany(TransactionItem::class);
    }
}