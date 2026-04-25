<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    public function store(Request $request)
    {
        // Validasi input sesuai field yang dibutuhkan
        $request->validate([
            'trx_type' => 'required|in:SALE,PURCHASE,ADJUSTMENT', // 
            'trx_date' => 'required|date',
            'payment_method' => 'required',
            'items' => 'required|array', // List barang yang dibeli
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric',
        ]);

        
        return DB::transaction(function () use ($request) {
            
            // 1. Simpan data utama ke tabel Transactions
            $transaction = Transaction::create([
                'user_id' => $request->user()->id ?? 1,
                'trx_type' => $request->trx_type,
                'trx_date' => $request->trx_date,
                'payment_method' => $request->payment_method,
                'paid_at' => now(),
                'total_amount' => 0, // Nanti diupdate setelah hitung item
            ]);

            $totalAmount = 0;

            // 2. Simpan setiap barang ke tabel TransactionItems
            foreach ($request->items as $item) {
                $linePrice = $item['quantity'] * $item['unit_price']; // 
                $totalAmount += $linePrice;

                TransactionItem::create([
                    'transaction_id' => $transaction->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'line_price' => $linePrice,
                ]);
            }

            // 3. Update total akhir di tabel Transactions setelah semua item dihitung
            $transaction->update(['total_amount' => $totalAmount]);

            return response()->json([
                'message' => 'Transaksi berhasil disimpan!',
                'data' => $transaction->load('items')
            ], 201);
        });
    }
}