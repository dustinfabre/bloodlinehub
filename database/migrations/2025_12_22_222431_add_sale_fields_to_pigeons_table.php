<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pigeons', function (Blueprint $table) {
            $table->boolean('for_sale')->default(false)->after('pedigree_image');
            $table->decimal('sale_price', 10, 2)->nullable()->after('for_sale');
            $table->boolean('hide_price')->default(false)->after('sale_price');
            $table->text('sale_description')->nullable()->after('hide_price');
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
