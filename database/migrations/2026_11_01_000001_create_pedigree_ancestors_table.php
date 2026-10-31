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
        // Ancestors that are not birds in the loft, stored on the nearest loft bird so its offspring inherit them.
        // path is relative to that bird: "SD" is its sire's dam, "SDS" that dam's sire. Its own parents stay on
        // the pigeons table (sire_name, dam_name, ...), so paths here are always two letters or longer.
        Schema::create('pedigree_ancestors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pigeon_id')->constrained()->cascadeOnDelete();
            $table->string('path', 8);
            $table->string('ring_number')->nullable();
            $table->string('name')->nullable();
            $table->string('color')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('color_tag_id')->nullable()->constrained('color_tags')->nullOnDelete();
            $table->timestamps();

            $table->unique(['pigeon_id', 'path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedigree_ancestors');
    }
};
