<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AiPortfolioInsight extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'ai_run_id',
        'user_id',
        'insight',
        'tanggal_laporan',
        'periode',
        'total_omset_minggu_ini',
        'total_transaksi',
        'rata_rata_transaksi_per_hari',
        'rata_rata_omset_per_hari',
        'bintang_warung',
        'hari_ramai_tanggal',
        'hari_ramai_omset',
        'produk_kurang_laku',
        'source',
        'generated_at',
        'valid_until',
    ];

    protected $casts = [
        'total_omset_minggu_ini' => 'decimal:2',
        'rata_rata_transaksi_per_hari' => 'decimal:2',
        'rata_rata_omset_per_hari' => 'decimal:2',
        'hari_ramai_omset' => 'decimal:2',
        'hari_ramai_tanggal' => 'date',
        'bintang_warung' => 'array',
        'produk_kurang_laku' => 'array',
        'generated_at' => 'datetime',
        'valid_until' => 'datetime',
    ];

    public function aiRun(): BelongsTo
    {
        return $this->belongsTo(AiRun::class, 'ai_run_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
