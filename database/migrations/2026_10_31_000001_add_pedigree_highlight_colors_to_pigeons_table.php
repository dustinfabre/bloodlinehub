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
     * Pedigree highlight colors for external (unlinked) sire and dam.
     * Linked parents use their own color_tag_id.
     */
    public function up(): void
    {
        Schema::table('pigeons', function (Blueprint $table) {
            $table->foreignId('sire_color_tag_id')->nullable()->after('sire_notes')->constrained('color_tags')->nullOnDelete();
            $table->foreignId('dam_color_tag_id')->nullable()->after('dam_notes')->constrained('color_tags')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pigeons', function (Blueprint $table) {
            $table->dropForeign(['sire_color_tag_id']);
            $table->dropForeign(['dam_color_tag_id']);
            $table->dropColumn(['sire_color_tag_id', 'dam_color_tag_id']);
        });
    }
};
