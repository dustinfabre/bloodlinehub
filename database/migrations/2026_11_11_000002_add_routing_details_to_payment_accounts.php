<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Disable transaction for Neon.tech pooling compatibility.
     */
    public $withinTransaction = false;

    public function up(): void
    {
        // US-style receiving details for international accounts (e.g. a Wise or Payoneer USD account)
        Schema::table('payment_accounts', function (Blueprint $table) {
            // checking | savings
            $table->string('account_type')->nullable()->after('account_number');
            // ACH/ABA routing number
            $table->string('routing_number')->nullable()->after('account_type');
        });
    }

    public function down(): void
    {
        Schema::table('payment_accounts', function (Blueprint $table) {
            $table->dropColumn(['account_type', 'routing_number']);
        });
    }
};
