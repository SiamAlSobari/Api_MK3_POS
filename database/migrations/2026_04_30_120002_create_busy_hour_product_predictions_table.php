<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('busy_hour_product_predictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hourly_prediction_id')->constrained('busy_hour_hourly_predictions')->cascadeOnDelete();
            $table->unsignedBigInteger('product_id');
            $table->string('product_name');
            $table->decimal('probability', 5, 3);
            $table->decimal('estimated_qty', 10, 1);
            $table->decimal('estimated_revenue', 15, 2);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('product_id')->references('id')->on('products');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('busy_hour_product_predictions');
    }
};
