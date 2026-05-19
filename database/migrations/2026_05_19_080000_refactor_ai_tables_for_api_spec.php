<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * REFACTOR: Menyesuaikan semua tabel AI agar sesuai dengan AI_API_SPEC.md
 *
 * Perubahan:
 * 1. ai_runs              → tambah type_ai 'PORTFOLIO', seasonal_insight JSON, total_products
 * 2. ai_recommendations   → tambah avg_daily_sales, restock range (min/max/label), urgency_description, stock_timeline JSON, product_name, product_price
 * 3. busy_hour_daily_forecasts → tambah range-based trx & revenue (min/max/label), peak_hour_label
 * 4. busy_hour_hourly_predictions → tambah range-based trx & revenue, busy_label, what_to_prepare
 * 5. NEW: ai_portfolio_insights → tabel baru untuk weekly portfolio LLM insights
 */
return new class extends Migration
{
    public function up(): void
    {
        // =====================================================================
        // 1. ai_runs: Tambah enum PORTFOLIO, seasonal_insight, total_products
        // =====================================================================
        // Ubah enum type_ai: STOCKS, BUSY, PORTFOLIO
        DB::statement("ALTER TABLE ai_runs MODIFY COLUMN type_ai ENUM('STOCKS','BUSY','PORTFOLIO') DEFAULT 'STOCKS'");

        Schema::table('ai_runs', function (Blueprint $table) {
            $table->json('seasonal_insight')->nullable()->after('error_message');
            $table->integer('total_products')->nullable()->after('seasonal_insight');
        });

        // =====================================================================
        // 2. ai_recommendations: Tambah kolom sesuai AI response
        // =====================================================================
        Schema::table('ai_recommendations', function (Blueprint $table) {
            // Denormalized product info (cache agar tidak perlu join terus)
            $table->string('product_name')->nullable()->after('product_id');
            $table->decimal('product_price', 15, 2)->nullable()->after('product_name');

            // Avg daily sales dari AI
            $table->decimal('avg_daily_sales', 10, 2)->nullable()->after('current_stock');

            // Restock recommendation range (menggantikan recommed_restok_qty yang single value)
            $table->integer('restock_min')->nullable()->after('recommed_restok_qty');
            $table->integer('restock_max')->nullable()->after('restock_min');
            $table->string('restock_label')->nullable()->after('restock_max');
            $table->integer('target_days_coverage')->nullable()->after('restock_label');

            // Urgency description (emoji-rich text dari AI)
            $table->text('urgency_description')->nullable()->after('risk_level');

            // Stock timeline per hari (JSON array)
            $table->json('stock_timeline')->nullable()->after('risk_point');
        });

        // =====================================================================
        // 3. busy_hour_daily_forecasts: Tambah range-based fields
        // =====================================================================
        Schema::table('busy_hour_daily_forecasts', function (Blueprint $table) {
            // Range-based estimated transactions
            $table->integer('est_trx_min')->nullable()->after('total_predicted_trx');
            $table->integer('est_trx_max')->nullable()->after('est_trx_min');
            $table->string('est_trx_label')->nullable()->after('est_trx_max');

            // Range-based estimated revenue
            $table->decimal('est_revenue_min', 15, 2)->nullable()->after('total_predicted_revenue');
            $table->decimal('est_revenue_max', 15, 2)->nullable()->after('est_revenue_min');
            $table->string('est_revenue_label')->nullable()->after('est_revenue_max');

            // Peak hour label (emoji text)
            $table->string('peak_hour_label')->nullable()->after('peak_hour');
        });

        // =====================================================================
        // 4. busy_hour_hourly_predictions: Tambah range-based & labels
        // =====================================================================
        Schema::table('busy_hour_hourly_predictions', function (Blueprint $table) {
            // Range-based estimated transactions
            $table->integer('est_trx_min')->nullable()->after('predicted_transactions');
            $table->integer('est_trx_max')->nullable()->after('est_trx_min');
            $table->string('est_trx_label')->nullable()->after('est_trx_max');

            // Range-based estimated revenue
            $table->decimal('est_revenue_min', 15, 2)->nullable()->after('predicted_revenue');
            $table->decimal('est_revenue_max', 15, 2)->nullable()->after('est_revenue_min');
            $table->string('est_revenue_label')->nullable()->after('est_revenue_max');

            // Busy label & what to prepare
            $table->string('busy_label')->nullable()->after('busy_level');
            $table->text('what_to_prepare')->nullable()->after('emoji');
        });

        // =====================================================================
        // 5. NEW TABLE: ai_portfolio_insights (Weekly Portfolio dari LLM)
        // =====================================================================
        Schema::create('ai_portfolio_insights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_run_id')->constrained('ai_runs')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // LLM-generated insight text
            $table->text('insight')->nullable();

            // Summary data (structured)
            $table->string('tanggal_laporan')->nullable();
            $table->string('periode')->nullable();
            $table->decimal('total_omset_minggu_ini', 15, 2)->default(0);
            $table->integer('total_transaksi')->default(0);
            $table->decimal('rata_rata_transaksi_per_hari', 10, 2)->default(0);
            $table->decimal('rata_rata_omset_per_hari', 15, 2)->default(0);

            // Bintang warung (top products) - JSON array
            $table->json('bintang_warung')->nullable();

            // Hari paling ramai
            $table->date('hari_ramai_tanggal')->nullable();
            $table->decimal('hari_ramai_omset', 15, 2)->nullable();

            // Produk kurang laku - JSON array
            $table->json('produk_kurang_laku')->nullable();

            // LLM metadata
            $table->string('source')->nullable(); // gemini-primary, groq-fallback, etc
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('valid_until')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        // 5. Drop new table
        Schema::dropIfExists('ai_portfolio_insights');

        // 4. Rollback busy_hour_hourly_predictions
        Schema::table('busy_hour_hourly_predictions', function (Blueprint $table) {
            $table->dropColumn([
                'est_trx_min', 'est_trx_max', 'est_trx_label',
                'est_revenue_min', 'est_revenue_max', 'est_revenue_label',
                'busy_label', 'what_to_prepare',
            ]);
        });

        // 3. Rollback busy_hour_daily_forecasts
        Schema::table('busy_hour_daily_forecasts', function (Blueprint $table) {
            $table->dropColumn([
                'est_trx_min', 'est_trx_max', 'est_trx_label',
                'est_revenue_min', 'est_revenue_max', 'est_revenue_label',
                'peak_hour_label',
            ]);
        });

        // 2. Rollback ai_recommendations
        Schema::table('ai_recommendations', function (Blueprint $table) {
            $table->dropColumn([
                'product_name', 'product_price',
                'avg_daily_sales',
                'restock_min', 'restock_max', 'restock_label', 'target_days_coverage',
                'urgency_description',
                'stock_timeline',
            ]);
        });

        // 1. Rollback ai_runs
        Schema::table('ai_runs', function (Blueprint $table) {
            $table->dropColumn(['seasonal_insight', 'total_products']);
        });

        DB::statement("ALTER TABLE ai_runs MODIFY COLUMN type_ai ENUM('STOCKS','BUSY') DEFAULT 'STOCKS'");
    }
};
