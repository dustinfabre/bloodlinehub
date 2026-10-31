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
        Schema::table('pedigrees', function (Blueprint $table) {
            $table->string('password')->nullable()->after('is_public');
            $table->timestamp('password_expires_at')->nullable()->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('pedigrees', function (Blueprint $table) {
            $table->dropColumn(['password', 'password_expires_at']);
        });
    }
};
