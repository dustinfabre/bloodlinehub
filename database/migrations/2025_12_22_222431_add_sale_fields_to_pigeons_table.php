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

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pigeons', function (Blueprint $table) {
            $table->boolean('for_sale')->default(false);
            $table->decimal('sale_price', 10, 2)->nullable();
            $table->boolean('hide_price')->default(false);
            $table->text('sale_description')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pigeons', function (Blueprint $table) {
            $table->dropColumn(['for_sale', 'sale_price', 'hide_price', 'sale_description']);
        });
    }
};
