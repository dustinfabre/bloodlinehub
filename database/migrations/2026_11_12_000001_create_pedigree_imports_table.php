<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pedigree sheets uploaded to be read (see App\Models\PedigreeImport).
     */
    public function up(): void
    {
        Schema::create('pedigree_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // pending, reading, ready, failed, saved or discarded
            $table->string('status', 20)->default('pending');
            // The uploaded sheet on the private disk, until the import is saved or discarded
            $table->string('file_path')->nullable();
            $table->string('file_mime', 50)->nullable();
            $table->string('file_name')->nullable();
            // What was read from the sheet, in the review form's shape
            $table->json('draft')->nullable();
            // Why the sheet could not be read
            $table->string('error', 500)->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            // Tokens the reading used, to keep an eye on the cost
            $table->json('usage')->nullable();
            // The bird it was saved to
            $table->foreignId('pigeon_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedigree_imports');
    }
};
