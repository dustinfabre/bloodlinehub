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
            $table->foreignId('color_tag_id')->nullable()->after('color')->constrained('color_tags')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pigeons', function (Blueprint $table) {
            $table->dropForeign(['color_tag_id']);
            $table->dropColumn('color_tag_id');
        });
    }
};
