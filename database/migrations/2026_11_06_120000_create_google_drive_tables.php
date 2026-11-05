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
        // A user's link to their own Google Drive, where their photos are stored (tokens are encrypted by the model)
        Schema::create('google_drive_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('google_email')->nullable();
            $table->text('refresh_token');
            $table->text('access_token')->nullable();
            $table->timestamp('access_token_expires_at')->nullable();
            // The "BloodlineHub" folder the app created in the user's Drive
            $table->string('folder_id')->nullable();
            $table->timestamps();
        });

        // One row per image saved to a user's Drive. The random id is the public /media/{id}.webp address, so the
        // Drive file id itself is never exposed.
        Schema::create('drive_files', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('drive_file_id');
            $table->string('mime_type');
            $table->unsignedInteger('size')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drive_files');
        Schema::dropIfExists('google_drive_connections');
    }
};
