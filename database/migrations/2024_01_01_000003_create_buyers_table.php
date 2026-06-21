<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * BUYERS TABLE
     * ------------
     * Stores the identity of each buyer linked to a unit.
     * A unit can have at most one active buyer at a time (enforced via unique index).
     * Historical buyer records are preserved via soft deletes.
     */
    public function up(): void
    {
        Schema::create('buyers', function (Blueprint $table) {
            $table->id();

            // ── Relationship ───────────────────────────────────────────────
            $table->foreignId('unit_id')
                  ->constrained('units')
                  ->restrictOnDelete();

            // ── Buyer Identity ─────────────────────────────────────────────
            $table->string('name');                         // Full legal name
            $table->string('id_number')->nullable();        // KTP / NIK number
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();

            // ── Contract Meta ──────────────────────────────────────────────
            $table->date('contract_date')->nullable();      // Date contract was signed
            $table->date('handover_date')->nullable();      // Projected/actual handover

            $table->boolean('is_active')->default(true);    // Current active buyer flag

            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buyers');
    }
};
