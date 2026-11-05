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
        // How buyers reach the seller, entered by the seller for this listing (phone, Facebook page, email...).
        // The public marketplace shows this rather than the seller's account email.
        Schema::table('sales', function (Blueprint $table) {
            $table->string('contact')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('contact');
        });
    }
};
