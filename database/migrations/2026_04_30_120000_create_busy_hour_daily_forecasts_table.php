<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('busy_hour_daily_forecasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_run_id')->constrained('ai_runs')->cascadeOnDelete();
            $table->date('forecast_date');
            $table->string('day_name', 20);
            $table->tinyInteger('day_of_week');
            $table->boolean('is_weekend');
            $table->decimal('total_predicted_trx', 10, 2);
            $table->decimal('total_predicted_revenue', 15, 2);
            $table->string('peak_hour', 10);
            $table->decimal('peak_hour_trx', 10, 2);
            $table->integer('busy_hours_count');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('busy_hour_daily_forecasts');
    }
};
