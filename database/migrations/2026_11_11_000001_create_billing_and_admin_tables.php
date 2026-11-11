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
        Schema::table('users', function (Blueprint $table) {
            // Can open /admin. Granted with `php artisan admin:grant {email}`, never through a form.
            $table->boolean('is_admin')->default(false);
            // Premium while this is in the future; extended each time a bank transfer is approved
            $table->timestamp('premium_until')->nullable();
        });

        // What can be bought and for how long, priced in pesos for local transfers and dollars for international ones
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->unsignedSmallInteger('duration_months');
            $table->decimal('price_php', 10, 2);
            // Null: not offered to international payers
            $table->decimal('price_usd', 10, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Where payers send money: local bank or e-wallet accounts (pesos) and international ones (dollars)
        Schema::create('payment_accounts', function (Blueprint $table) {
            $table->id();
            // local | international
            $table->string('region');
            // Bank or wallet, e.g. "BDO", "GCash", "Wise"
            $table->string('bank_name');
            $table->string('account_name');
            $table->string('account_number');
            // International transfers: SWIFT/BIC, bank address and the currency it receives
            $table->string('swift_code')->nullable();
            $table->string('bank_address')->nullable();
            $table->string('currency', 3);
            $table->text('instructions')->nullable();
            // Optional QR code (e.g. InstaPay/QR Ph), a private file on R2
            $table->string('qr_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // A bank transfer a user made for a plan, with their receipt, waiting for an admin to check it
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            // Kept when the account is deleted: payment records are business records
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('payer_name');
            $table->string('payer_email');
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            // The plan as it was when paid for, so later price changes don't rewrite history
            $table->string('plan_name');
            $table->unsignedSmallInteger('duration_months');
            $table->foreignId('payment_account_id')->nullable()->constrained()->nullOnDelete();
            // The account paid to, as it read then (e.g. "BDO · 0012 3456 7890")
            $table->string('paid_to');
            $table->string('currency', 3);
            $table->decimal('amount', 10, 2);
            $table->string('reference_number')->nullable();
            $table->date('paid_on');
            $table->text('payer_note')->nullable();
            // Private file on R2
            $table->string('receipt_path');
            $table->string('receipt_mime');
            // pending | approved | rejected
            $table->string('status')->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            // Shown to the payer when a payment is rejected
            $table->text('review_note')->nullable();
            // The premium period an approval granted
            $table->timestamp('premium_from')->nullable();
            $table->timestamp('premium_until')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });

        // Messages sent from the feedback page, read in the admin inbox
        Schema::create('feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // bug | idea | question | other
            $table->string('category');
            $table->text('message');
            // The page the user was on when they opened the feedback page
            $table->string('page_url', 2048)->nullable();
            $table->string('user_agent', 512)->nullable();
            // Optional screenshot, a private file on R2
            $table->string('screenshot_path')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['archived_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('payment_accounts');
        Schema::dropIfExists('plans');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_admin', 'premium_until']);
        });
    }
};
