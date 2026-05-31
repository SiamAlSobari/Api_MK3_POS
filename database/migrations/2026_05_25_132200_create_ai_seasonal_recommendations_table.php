<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Create the new ai_seasonal_recommendations table
        Schema::create('ai_seasonal_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_recommendation_id')
                ->constrained('ai_recommendations')
                ->cascadeOnDelete();
            $table->integer('min')->nullable();
            $table->integer('max')->nullable();
            $table->string('label')->nullable();
            $table->string('holiday')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // 2. Migrate existing seasonal data from ai_recommendations if there is any
        $recommendations = DB::table('ai_recommendations')
            ->whereNotNull('seasonal_min')
            ->orWhereNotNull('seasonal_max')
            ->orWhereNotNull('seasonal_holiday')
            ->orWhereNotNull('seasonal_reason')
            ->get();

        foreach ($recommendations as $rec) {
            DB::table('ai_seasonal_recommendations')->insert([
                'ai_recommendation_id' => $rec->id,
                'min' => $rec->seasonal_min,
                'max' => $rec->seasonal_max,
                'label' => $rec->seasonal_label,
                'holiday' => $rec->seasonal_holiday,
                'reason' => $rec->seasonal_reason,
                'created_at' => $rec->created_at ?? now(),
                'updated_at' => $rec->updated_at ?? now(),
            ]);
        }

        // 3. Drop seasonal fields from the ai_recommendations table
        Schema::table('ai_recommendations', function (Blueprint $table) {
            $table->dropColumn([
                'seasonal_min',
                'seasonal_max',
                'seasonal_label',
                'seasonal_holiday',
                'seasonal_reason',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Add back seasonal columns to ai_recommendations table
        Schema::table('ai_recommendations', function (Blueprint $table) {
            $table->integer('seasonal_min')->nullable()->after('stock_timeline');
            $table->integer('seasonal_max')->nullable()->after('seasonal_min');
            $table->string('seasonal_label')->nullable()->after('seasonal_max');
            $table->string('seasonal_holiday')->nullable()->after('seasonal_label');
            $table->text('seasonal_reason')->nullable()->after('seasonal_holiday');
        });

        // 2. Move data back from ai_seasonal_recommendations to ai_recommendations
        if (Schema::hasTable('ai_seasonal_recommendations')) {
            $seasonalRecs = DB::table('ai_seasonal_recommendations')->get();
            foreach ($seasonalRecs as $sRec) {
                DB::table('ai_recommendations')
                    ->where('id', $sRec->ai_recommendation_id)
                    ->update([
                        'seasonal_min' => $sRec->min,
                        'seasonal_max' => $sRec->max,
                        'seasonal_label' => $sRec->label,
                        'seasonal_holiday' => $sRec->holiday,
                        'seasonal_reason' => $sRec->reason,
                    ]);
            }
        }

        // 3. Drop the ai_seasonal_recommendations table
        Schema::dropIfExists('ai_seasonal_recommendations');
    }
};
