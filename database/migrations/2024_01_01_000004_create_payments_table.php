<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PAYMENTS TABLE
     * --------------
     * Records every individual installment payment submitted by APP (the developer).
     * This is the core audit ledger. Every row is one payment event.
     *
     * NIS Audit Math (all computed at model/service level, stored for audit trail):
     * ─────────────────────────────────────────────────────────────────────────────
     *   variance             = actual_amount - target_amount
     *   supposed_nis_share   = target_amount  * 0.30
     *   actual_nis_share     = actual_amount  * 0.30
     *   payment_status       = 'correct' | 'underpay' | 'overpay'
     *
     * Land Ceiling (Kavling) tracking:
     *   cumulative_nis_share = running sum of actual_nis_share for this unit
     *   land_ceiling_overflow= true if cumulative_nis_share > unit.kavling_value
     *   capped_nis_share     = min(actual_nis_share, remaining kavling headroom)
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            // ── Relationships ──────────────────────────────────────────────
            $table->foreignId('unit_id')
                  ->constrained('units')
                  ->restrictOnDelete();

            $table->foreignId('buyer_id')
                  ->constrained('buyers')
                  ->restrictOnDelete();

            // ── Installment Identity ───────────────────────────────────────
            $table->unsignedSmallInteger('installment_number');
            // ^ Sequential slot (1, 2, 3 … max_installments). Unique per unit.

            $table->date('payment_date');                   // Date payment was made
            $table->date('due_date')->nullable();           // Expected due date for this slot

            // ── Core Amounts ───────────────────────────────────────────────
            $table->unsignedBigInteger('target_amount');
            // ^ = unit.angsuran_per_bulan at the time of this payment (snapshot for history)

            $table->unsignedBigInteger('actual_amount');
            // ^ = what APP actually reported was paid

            // ── Derived Audit Fields (stored for performance & audit trail) ─
            $table->bigInteger('variance');
            // ^ actual_amount - target_amount (can be negative for underpay)

            $table->enum('payment_status', [
                'correct',  // actual == target
                'underpay', // actual < target
                'overpay',  // actual > target
            ]);

            $table->unsignedBigInteger('supposed_nis_share');
            // ^ target_amount * 0.30

            $table->unsignedBigInteger('actual_nis_share');
            // ^ actual_amount * 0.30

            // ── Kavling (Land Ceiling) Tracking ────────────────────────────
            $table->unsignedBigInteger('cumulative_nis_share')->default(0);
            // ^ Running total of actual_nis_share for this unit AFTER this payment

            $table->unsignedBigInteger('capped_nis_share')->default(0);
            // ^ The effective NIS share after applying kavling ceiling
            // = min(actual_nis_share, max(0, kavling_value - previous_cumulative))

            $table->boolean('land_ceiling_overflow')->default(false);
            // ^ true if this payment caused/exceeded the kavling cap

            $table->boolean('land_cleared_on_this_payment')->default(false);
            // ^ true if THIS specific payment is the one that hit the exact ceiling

            // ── Submission Metadata ────────────────────────────────────────
            $table->string('reported_by')->nullable();      // APP staff/system identifier
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // ── Constraints ────────────────────────────────────────────────
            // Each installment slot can only be recorded once per unit
            $table->unique(['unit_id', 'installment_number'], 'unique_installment_per_unit');

            // Indexes for common query patterns
            $table->index(['unit_id', 'payment_date']);
            $table->index('payment_status');
            $table->index('land_ceiling_overflow');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
