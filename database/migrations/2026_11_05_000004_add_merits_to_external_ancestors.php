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
        // Merits of ancestors that are not in the loft, as a list of {type, level, year, detail}.
        // External parents live on the pigeon itself; deeper ancestors in pedigree_ancestors.
        Schema::table('pigeons', function (Blueprint $table) {
            $table->json('sire_merits')->nullable();
            $table->json('dam_merits')->nullable();
        });

        Schema::table('pedigree_ancestors', function (Blueprint $table) {
            $table->json('merits')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pigeons', function (Blueprint $table) {
            $table->dropColumn(['sire_merits', 'dam_merits']);
        });

        Schema::table('pedigree_ancestors', function (Blueprint $table) {
            $table->dropColumn('merits');
        });
    }
};
