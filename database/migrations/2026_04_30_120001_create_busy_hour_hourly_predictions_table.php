<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('busy_hour_hourly_predictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_forecast_id')->constrained('busy_hour_daily_forecasts')->cascadeOnDelete();
            $table->string('hour', 10);
            $table->decimal('predicted_transactions', 10, 2);
            $table->decimal('predicted_revenue', 15, 2);
            $table->enum('busy_level', ['PEAK', 'HIGH', 'MEDIUM', 'LOW', 'CLOSED']);
            $table->string('emoji', 10);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('busy_hour_hourly_predictions');
    }
};
