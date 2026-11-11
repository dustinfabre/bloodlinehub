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
        Schema::table('payment_accounts', function (Blueprint $table) {
            // bank (a bank or e-wallet account) | paypal (international only; account_number holds the PayPal email)
            $table->string('method')->default('bank')->after('region');
        });
    }

    public function down(): void
    {
        Schema::table('payment_accounts', function (Blueprint $table) {
            $table->dropColumn('method');
        });
    }
};
