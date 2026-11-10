<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pigeons', function (Blueprint $table) {
            // Who bred the bird, for a bird the user owns but didn't breed (not both parents in their loft)
            $table->string('bred_by')->nullable()->after('personal_number');
            // Which relative each of pedigree_images shows, by chart position ('S' sire, 'SD' sire's dam, ...);
            // images without one are certificates or other documents
            $table->json('pedigree_image_relations')->nullable()->after('pedigree_images');
        });
    }

    public function down(): void
    {
        Schema::table('pigeons', function (Blueprint $table) {
            $table->dropColumn(['bred_by', 'pedigree_image_relations']);
        });
    }
};
