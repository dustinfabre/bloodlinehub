<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentAccount;
use App\Services\PrivateFiles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The bank and e-wallet accounts shown on the billing page: local ones for pesos, international ones for dollars.
 */
class PaymentAccountController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/BankAccounts', [
            'accounts' => PaymentAccount::ordered()->withCount('payments')->get()
                ->map(fn (PaymentAccount $account) => [
                    ...$account->toPayerArray(),
                    'is_active' => $account->is_active,
                    'sort_order' => $account->sort_order,
                    'payments_count' => $account->payments_count,
                ]),
        ]);
    }

    public function store(Request $request, PrivateFiles $files): RedirectResponse
    {
        $account = new PaymentAccount($this->validated($request));
        $this->saveQr($request, $account, $files);
        $account->save();

        return back();
    }

    public function update(Request $request, PaymentAccount $paymentAccount, PrivateFiles $files): RedirectResponse
    {
        $paymentAccount->fill($this->validated($request));
        $this->saveQr($request, $paymentAccount, $files);
        $paymentAccount->save();

        return back();
    }

    /**
     * Past payments keep the account's details as they read then (payments.paid_to), so deleting is safe.
     */
    public function destroy(PaymentAccount $paymentAccount, PrivateFiles $files): RedirectResponse
    {
        $qr = $paymentAccount->qr_path;
        $paymentAccount->delete();
        $files->delete($qr);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $paypal = $request->input('method') === PaymentAccount::PAYPAL;

        $validated = $request->validate([
            'region' => ['required', Rule::in(PaymentAccount::REGIONS)],
            'method' => ['nullable', Rule::in(PaymentAccount::METHODS)],
            // PayPal's name is set by the model
            'bank_name' => [Rule::requiredIf(! $paypal), 'nullable', 'string', 'max:100'],
            'account_name' => ['required', 'string', 'max:150'],
            // For PayPal: the PayPal email
            'account_number' => ['required', 'string', 'max:100', ...($paypal ? ['email'] : [])],
            // International (US-style) receiving details; local accounts don't use them
            'account_type' => ['nullable', Rule::in(PaymentAccount::ACCOUNT_TYPES)],
            'routing_number' => ['nullable', 'string', 'max:50'],
            'swift_code' => ['nullable', 'string', 'max:20'],
            'bank_address' => ['nullable', 'string', 'max:255'],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'qr' => ['nullable', ...PrivateFiles::imageRules()],
            'remove_qr' => ['boolean'],
        ], [
            'account_number.email' => 'Enter the email address of the PayPal account.',
        ]);

        $international = $validated['region'] === PaymentAccount::INTERNATIONAL;

        return [
            ...collect($validated)->except(['qr', 'remove_qr'])->all(),
            'method' => $paypal ? PaymentAccount::PAYPAL : PaymentAccount::BANK,
            // The model names PayPal accounts itself
            'bank_name' => $paypal ? 'PayPal' : $validated['bank_name'],
            // Kept only on international accounts, so switching an account to local clears them
            'account_type' => $international ? ($validated['account_type'] ?? null) : null,
            'routing_number' => $international ? ($validated['routing_number'] ?? null) : null,
            'swift_code' => $international ? ($validated['swift_code'] ?? null) : null,
            'bank_address' => $international ? ($validated['bank_address'] ?? null) : null,
            'is_active' => $request->boolean('is_active'),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ];
    }

    private function saveQr(Request $request, PaymentAccount $account, PrivateFiles $files): void
    {
        if (! $request->hasFile('qr') && ! $request->boolean('remove_qr')) {
            return;
        }

        $files->delete($account->qr_path);
        $account->qr_path = $request->hasFile('qr')
            // Small enough to scan from a phone screen, large enough to stay sharp
            ? $files->store($request->file('qr'), 'payment-qr', 1200, 'qr')['path']
            : null;
    }
}
