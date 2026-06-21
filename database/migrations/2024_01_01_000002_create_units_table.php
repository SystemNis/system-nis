<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * UNITS TABLE
     * -----------
     * Stores permanent/immutable physical attributes of each house unit.
     * These values define the Kavling cap and are set once at project registration.
     *
     * Key formula stored here:
     *   kavling_value = luas_tanah * 4_000_000   (computed at model level)
     */
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();

            // ── Relationships ──────────────────────────────────────────────
            $table->foreignId('cluster_id')
                  ->constrained('clusters')
                  ->restrictOnDelete();

            // ── Unit Identity ──────────────────────────────────────────────
            $table->string('block');                    // e.g. "A", "B", "C"
            $table->string('unit_number');              // e.g. "01", "02", "0"
            $table->string('house_type');               // e.g. "STANDARD", "PREMIUM", "TYPE-36"

            // ── Physical Dimensions ────────────────────────────────────────
            $table->unsignedInteger('luas_bangunan');   // LB — building area in m²
            $table->unsignedInteger('luas_tanah');      // LT — land area in m²

            // ── Financial Attributes ───────────────────────────────────────
            $table->enum('payment_type', [
                'Cash Keras',       // Full upfront cash
                'KPR',              // Bank mortgage
                'Cash Bertahap',    // Installment cash (CB)
            ]);

            $table->unsignedBigInteger('harga_penjualan');  // Total contract price (Rp)
            $table->unsignedBigInteger('down_payment')->default(0); // DP amount (Rp)

            // angsuran_per_bulan = (harga_penjualan - down_payment) / max_installments
            // Stored redundantly for quick access and historical integrity
            $table->unsignedBigInteger('angsuran_per_bulan')->default(0);

            $table->unsignedSmallInteger('max_installments')->default(1);
            // ^ Max installment count (e.g. 10 for CB-10, 120 for KPR-10yr, 1 for Cash Keras)

            // ── Status ─────────────────────────────────────────────────────
            $table->enum('status', [
                'active',       // Unit has active buyer, payments ongoing
                'settled',      // All installments completed, kavling possibly cleared
                'land_cleared', // Kavling cap fully reached — NIS land share done
                'unpaid',       // No payments yet recorded
                'cancelled',    // Contract cancelled
            ])->default('unpaid');

            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // ── Composite Unique Constraint ────────────────────────────────
            // A block + unit_number combo must be unique within a cluster
            $table->unique(['cluster_id', 'block', 'unit_number'], 'unique_unit_per_cluster');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
