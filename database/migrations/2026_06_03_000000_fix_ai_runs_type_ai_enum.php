<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE ai_runs DROP CONSTRAINT IF EXISTS ai_runs_type_ai_check");
            DB::statement("ALTER TABLE ai_runs ADD CONSTRAINT ai_runs_type_ai_check CHECK (type_ai IN ('STOCKS', 'BUSY', 'PORTFOLIO'))");
        } elseif (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE ai_runs MODIFY COLUMN type_ai ENUM('STOCKS','BUSY','PORTFOLIO') DEFAULT 'STOCKS'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Note: If there are existing 'PORTFOLIO' records, dropping the constraint or altering the column might fail.
        // But for rollback safety:
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE ai_runs DROP CONSTRAINT IF EXISTS ai_runs_type_ai_check");
            DB::statement("ALTER TABLE ai_runs ADD CONSTRAINT ai_runs_type_ai_check CHECK (type_ai IN ('STOCKS', 'BUSY'))");
        } elseif (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE ai_runs MODIFY COLUMN type_ai ENUM('STOCKS','BUSY') DEFAULT 'STOCKS'");
        }
    }
};
