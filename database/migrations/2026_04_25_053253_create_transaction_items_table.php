<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
{
    Schema::create('transaction_items', function (Blueprint $table) {
        $table->id(); // ID item 
        $table->foreignId('transaction_id')->constrained()->onDelete('cascade'); // relasi ke transaksi 
        $table->foreignId('product_id')->constrained(); // produk yang dibeli 
        $table->integer('quantity'); // jumlah barang 
        $table->decimal('unit_price', 15, 2); // harga per item 
        $table->decimal('line_price', 15, 2); // total (qty x harga) 
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_items');
    }
};
