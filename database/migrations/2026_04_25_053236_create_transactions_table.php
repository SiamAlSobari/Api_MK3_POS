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
    Schema::create('transactions', function (Blueprint $table) {
        $table->id(); // ID unik transaksi 
        $table->foreignId('user_id')->constrained(); // kasir yang melakukan transaksi 
        $table->enum('trx_type', ['SALE', 'PURCHASE', 'ADJUSTMENT']); 
        $table->date('trx_date'); // tanggal transaksi 
        $table->string('payment_method'); // CASH, QRIS, TRANSFER 
        $table->timestamp('paid_at')->nullable(); // waktu pembayaran 
        $table->decimal('total_amount', 15, 2); // total akhir 
        $table->timestamps(); // created_at dan updated_at 
        $table->softDeletes(); // deleted_at untuk soft delete 
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
