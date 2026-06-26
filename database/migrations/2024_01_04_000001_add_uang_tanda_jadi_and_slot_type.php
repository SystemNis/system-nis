<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->unsignedBigInteger('uang_tanda_jadi')
                  ->nullable()
                  ->after('down_payment');
        });

        Schema::table('payments', function (Blueprint $table) {
            // 'regular' = normal numbered slot
            // 'kpr'     = the special KPR bank disbursement slot
            $table->enum('slot_type', ['regular', 'kpr'])
                  ->default('regular')
                  ->after('installment_number');
        });
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn('uang_tanda_jadi');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('slot_type');
        });
    }
};
