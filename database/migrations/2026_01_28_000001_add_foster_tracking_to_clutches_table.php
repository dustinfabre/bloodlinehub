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
        Schema::table('clutches', function (Blueprint $table) {
            $table->boolean('is_fostered')->default(false)->after('status');
            $table->foreignId('biological_pairing_id')->nullable()->after('is_fostered')
                  ->constrained('pairings')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clutches', function (Blueprint $table) {
            $table->dropForeign(['biological_pairing_id']);
            $table->dropColumn(['is_fostered', 'biological_pairing_id']);
        });
    }
};
