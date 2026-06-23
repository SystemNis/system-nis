<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Prepares the units table for the upcoming bulk-import feature.
     *
     * 1. Adds `tahap` (stage) — a new permanent attribute, sits logically
     *    before cluster/unit in the identity hierarchy: Tahap → Cluster → Unit.
     *    Three fixed stages for now: Tahap 1, Tahap 2, Tahap 3.
     *
     * 2. Relaxes every column except the four "hard identity" fields to be
     *    optional, so a house can be registered with just the bare minimum
     *    and filled in progressively (or bulk-imported with partial data):
     *      STILL REQUIRED : cluster_id, block, unit_number, luas_tanah
     *      NOW OPTIONAL   : house_type, luas_bangunan, payment_type,
     *                       harga_penjualan, down_payment, angsuran_per_bulan,
     *                       max_installments
     *
     * NOTE: requires doctrine/dbal (non-dev) for the ->change() calls below,
     * same as the earlier payment_date-nullable migration.
     */
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            // Tahap sits right after the primary key, before cluster_id,
            // matching the requested hierarchy: Tahap → Cluster → Unit.
            $table->enum('tahap', ['Tahap 1', 'Tahap 2', 'Tahap 3'])
                  ->nullable()
                  ->after('id');
        });

        Schema::table('units', function (Blueprint $table) {
            $table->string('house_type')->nullable()->change();
            $table->unsignedInteger('luas_bangunan')->nullable()->change();
            $table->enum('payment_type', ['Cash Keras', 'KPR', 'Cash Bertahap'])
                  ->nullable()
                  ->change();
            $table->unsignedBigInteger('harga_penjualan')->nullable()->change();
            $table->unsignedBigInteger('down_payment')->nullable()->change();
            $table->unsignedBigInteger('angsuran_per_bulan')->nullable()->change();
            $table->unsignedSmallInteger('max_installments')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn('tahap');
        });

        Schema::table('units', function (Blueprint $table) {
            $table->string('house_type')->nullable(false)->change();
            $table->unsignedInteger('luas_bangunan')->nullable(false)->change();
            $table->enum('payment_type', ['Cash Keras', 'KPR', 'Cash Bertahap'])
                  ->nullable(false)
                  ->change();
            $table->unsignedBigInteger('harga_penjualan')->nullable(false)->change();
            $table->unsignedBigInteger('down_payment')->default(0)->nullable(false)->change();
            $table->unsignedBigInteger('angsuran_per_bulan')->default(0)->nullable(false)->change();
            $table->unsignedSmallInteger('max_installments')->default(1)->nullable(false)->change();
        });
    }
};
