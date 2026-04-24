<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create("stocks", function (Blueprint $table) {
            $table->uuid("id")->primary();
            $table
                ->foreignId("product_id")
                ->constrained("products")
                ->cascadeOnDelete();
            $table->integer("stock_on_hand");
            $table->timestamps();
            $table->softDeletes();

            $table->index("product_id");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists("stocks");
    }
};
