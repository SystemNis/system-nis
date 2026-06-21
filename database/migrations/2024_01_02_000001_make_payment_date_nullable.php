<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Make payment_date optional. APP sometimes reports a payment before
     * the exact date is confirmed — the audit math (variance, NIS share,
     * kavling ceiling) does not depend on the date, so it should not block
     * recording a payment.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->date('payment_date')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->date('payment_date')->nullable(false)->change();
        });
    }
};
