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
        Schema::table('feedback', function (Blueprint $table) {
            $table->timestamp('starred_at')->nullable()->after('archived_at');
            // Where it stands, in words that fit its category (Feedback::STATUSES); "new" until an admin moves it
            $table->string('status')->default('new')->after('category');
            $table->timestamp('status_changed_at')->nullable()->after('status');
            // "Me too": the same request as an earlier message, which then counts this sender too
            $table->foreignId('duplicate_of_id')->nullable()->after('status_changed_at')
                ->constrained('feedback')->nullOnDelete();
            // Private to admins
            $table->text('admin_note')->nullable()->after('screenshot_path');

            $table->index(['category', 'status']);
        });

        // Labels admins make to group messages (e.g. "pedigree", "billing", "mobile")
        Schema::create('feedback_labels', function (Blueprint $table) {
            $table->id();
            $table->string('name', 40)->unique();
            // A name from FeedbackLabel::COLORS
            $table->string('color', 20)->default('gray');
            $table->timestamps();
        });

        Schema::create('feedback_feedback_label', function (Blueprint $table) {
            $table->foreignId('feedback_id')->constrained('feedback')->cascadeOnDelete();
            $table->foreignId('feedback_label_id')->constrained()->cascadeOnDelete();

            $table->primary(['feedback_id', 'feedback_label_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_feedback_label');
        Schema::dropIfExists('feedback_labels');

        Schema::table('feedback', function (Blueprint $table) {
            $table->dropIndex(['category', 'status']);
            $table->dropConstrainedForeignId('duplicate_of_id');
            $table->dropColumn(['starred_at', 'status', 'status_changed_at', 'admin_note']);
        });
    }
};
