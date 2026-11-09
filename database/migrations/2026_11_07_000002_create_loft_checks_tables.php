<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A count of the birds in part of the loft (e.g. after a training toss), and what it found
        Schema::create('loft_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // What was counted, as it read at the time (e.g. "Young bird loft · PHA-2026")
            $table->string('label');
            // The filters counted: location, ring series and statuses, so the same group can be counted again
            $table->json('scope');
            $table->text('note')->nullable();
            $table->unsignedInteger('expected_count');
            $table->unsignedInteger('present_count');
            $table->unsignedInteger('missing_count');
            $table->unsignedInteger('unchecked_count');
            $table->unsignedInteger('returned_count');
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        // Each bird in a check: present, missing (marked missing by the check), unchecked (not seen, left as it
        // was) or returned (was missing and came back)
        Schema::create('loft_check_pigeon', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loft_check_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pigeon_id')->constrained()->cascadeOnDelete();
            $table->string('result');

            $table->unique(['loft_check_id', 'pigeon_id']);
        });

        // Every change of a pigeon's status, so the app knows when a bird went missing or came back
        Schema::create('pigeon_status_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pigeon_id')->constrained()->cascadeOnDelete();
            // The loft check that made the change, if any
            $table->foreignId('loft_check_id')->nullable()->constrained()->nullOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->timestamp('changed_at');

            $table->index(['pigeon_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pigeon_status_changes');
        Schema::dropIfExists('loft_check_pigeon');
        Schema::dropIfExists('loft_checks');
    }
};
