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
        Schema::table('transactions', function (Blueprint $table) {
            $table->index(['user_id', 'trx_type', 'trx_date'], 'idx_transactions_user_type_date');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->index(['user_id', 'plan_name', 'status', 'end_date'], 'idx_subscriptions_user_plan_status_end');
        });

        Schema::table('ai_runs', function (Blueprint $table) {
            $table->index(['user_id', 'type_ai', 'created_at'], 'idx_ai_runs_user_type_created');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('idx_transactions_user_type_date');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex('idx_subscriptions_user_plan_status_end');
        });

        Schema::table('ai_runs', function (Blueprint $table) {
            $table->dropIndex('idx_ai_runs_user_type_created');
        });
    }
};
