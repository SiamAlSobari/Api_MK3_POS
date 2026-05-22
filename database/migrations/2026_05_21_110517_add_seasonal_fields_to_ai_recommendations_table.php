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
        Schema::table("ai_recommendations", function (Blueprint $table) {
            $table
                ->integer("seasonal_min")
                ->nullable()
                ->after("stock_timeline");
            $table->integer("seasonal_max")->nullable()->after("seasonal_min");
            $table->string("seasonal_label")->nullable()->after("seasonal_max");
            $table
                ->string("seasonal_holiday")
                ->nullable()
                ->after("seasonal_label");
            $table
                ->text("seasonal_reason")
                ->nullable()
                ->after("seasonal_holiday");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table("ai_recommendations", function (Blueprint $table) {
            $table->dropColumn([
                "seasonal_min",
                "seasonal_max",
                "seasonal_label",
                "seasonal_holiday",
                "seasonal_reason",
            ]);
        });
    }
};
