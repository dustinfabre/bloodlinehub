<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\Plan;
use App\Services\PrivateFiles;
use App\Support\PremiumFeature;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Plan & billing: the user's plan, where to send a bank transfer, and the form to upload its receipt. There is no
 * payment gateway; an admin checks each transfer and approves it in /admin/payments.
 */
class BillingController extends Controller
{
    // Receipts waiting for review that one user may have at a time, so the form can't fill the bucket
    public const MAX_PENDING = 3;

    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('billing/Index', [
            'premium' => [
                'active' => $user->isPremium(),
                'until' => $user->premium_until?->toIso8601String(),
            ],
            'plans' => Plan::where('is_active', true)->ordered()
                ->get(['id', 'name', 'description', 'duration_months', 'price_php', 'price_usd']),
            'accounts' => PaymentAccount::where('is_active', true)->ordered()->get()
                ->map->toPayerArray()->values(),
            'payments' => $user->payments()->latest()->latest('id')->get()
                ->map(fn (Payment $payment) => [
                    'id' => $payment->id,
                    'plan_name' => $payment->plan_name,
                    'currency' => $payment->currency,
                    'amount' => $payment->amount,
                    'reference_number' => $payment->reference_number,
                    'paid_on' => $payment->paid_on?->toDateString(),
                    'paid_to' => $payment->paid_to,
                    'status' => $payment->status,
                    'review_note' => $payment->status === Payment::REJECTED ? $payment->review_note : null,
                    'premium_until' => $payment->premium_until?->toIso8601String(),
                    'created_at' => $payment->created_at->toIso8601String(),
                ]),
            'maxPending' => self::MAX_PENDING,
        ]);
    }

    public function store(Request $request, PrivateFiles $files): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'plan_id' => ['required', 'integer', Rule::exists('plans', 'id')->where('is_active', true)],
            'payment_account_id' => ['required', 'integer', Rule::exists('payment_accounts', 'id')->where('is_active', true)],
            'amount' => ['required', 'numeric', 'min:1', 'max:99999999'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'paid_on' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:'.now()->subDays(90)->toDateString()],
            'payer_note' => ['nullable', 'string', 'max:1000'],
            'receipt' => ['required', ...PrivateFiles::receiptRules()],
        ], [
            'paid_on.before_or_equal' => 'The payment date can\'t be in the future.',
            'paid_on.after_or_equal' => 'Payments older than 90 days can\'t be submitted here. Please send us feedback instead.',
            'receipt.mimes' => 'Upload the receipt as a photo, a screenshot or a PDF.',
            'receipt.max' => 'The receipt must be smaller than 10 MB.',
        ]);

        $plan = Plan::findOrFail($validated['plan_id']);
        $account = PaymentAccount::findOrFail($validated['payment_account_id']);

        if ($plan->priceIn($account->currency) === null) {
            throw ValidationException::withMessages([
                'payment_account_id' => "{$plan->name} can't be paid in {$account->currency}. Pick another account.",
            ]);
        }

        if ($user->payments()->pending()->count() >= self::MAX_PENDING) {
            throw ValidationException::withMessages([
                'receipt' => 'You already have '.self::MAX_PENDING.' payments waiting for review. Please wait until we check them.',
            ]);
        }

        $receipt = $files->store($request->file('receipt'), 'receipts/'.now()->format('Y/m'), field: 'receipt');

        $user->payments()->create([
            'payer_name' => $user->name,
            'payer_email' => $user->email,
            'plan_id' => $plan->id,
            'plan_name' => $plan->name,
            'duration_months' => $plan->duration_months,
            'payment_account_id' => $account->id,
            'paid_to' => $account->label(),
            'currency' => $account->currency,
            'amount' => $validated['amount'],
            'reference_number' => $validated['reference_number'] ?? null,
            'paid_on' => $validated['paid_on'],
            'payer_note' => $validated['payer_note'] ?? null,
            'receipt_path' => $receipt['path'],
            'receipt_mime' => $receipt['mime'],
        ]);

        return back();
    }

    /**
     * What a Premium feature does, with the way to get Premium (or a note that the user already has it).
     */
    public function feature(string $feature): Response
    {
        return Inertia::render('premium/Required', ['feature' => PremiumFeature::from($feature)->toArray()]);
    }

    /**
     * The receipt, for the payer and for admins.
     */
    public function receipt(Request $request, Payment $payment, PrivateFiles $files): HttpResponse
    {
        $user = $request->user();

        abort_unless($user->is_admin || $payment->user_id === $user->id, 404);

        return $files->stream($payment->receipt_path, $payment->receipt_mime, "receipt {$payment->id}");
    }

    /**
     * An account's QR code, for anyone signed in (inactive accounts only for admins).
     */
    public function qr(Request $request, PaymentAccount $paymentAccount, PrivateFiles $files): HttpResponse
    {
        abort_unless($paymentAccount->qr_path && ($paymentAccount->is_active || $request->user()->is_admin), 404);

        return $files->stream($paymentAccount->qr_path, 'image/webp', "{$paymentAccount->bank_name} QR");
    }
}
