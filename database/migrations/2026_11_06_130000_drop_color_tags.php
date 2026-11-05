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
     * Highlight colors (each user's own color tags) are replaced by the fixed merit colors, so the tags and every
     * reference to them go. This deletes the saved highlight colors; rolling back restores the columns empty.
     */
    public function up(): void
    {
        Schema::table('pigeons', function (Blueprint $table) {
            $table->dropConstrainedForeignId('color_tag_id');
            $table->dropConstrainedForeignId('sire_color_tag_id');
            $table->dropConstrainedForeignId('dam_color_tag_id');
        });

        Schema::table('pedigree_ancestors', function (Blueprint $table) {
            $table->dropConstrainedForeignId('color_tag_id');
        });

        Schema::dropIfExists('color_tags');
    }

    public function down(): void
    {
        Schema::create('color_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('color', 7);
            $table->timestamps();

            $table->unique(['user_id', 'name']);
        });

        Schema::table('pigeons', function (Blueprint $table) {
            $table->foreignId('color_tag_id')->nullable()->after('color')->constrained('color_tags')->nullOnDelete();
            $table->foreignId('sire_color_tag_id')->nullable()->after('sire_notes')->constrained('color_tags')->nullOnDelete();
            $table->foreignId('dam_color_tag_id')->nullable()->after('dam_notes')->constrained('color_tags')->nullOnDelete();
        });

        Schema::table('pedigree_ancestors', function (Blueprint $table) {
            $table->foreignId('color_tag_id')->nullable()->constrained('color_tags')->nullOnDelete();
        });
    }
};
