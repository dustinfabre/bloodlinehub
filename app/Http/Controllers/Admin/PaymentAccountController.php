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
        $validated = $request->validate([
            'region' => ['required', Rule::in(PaymentAccount::REGIONS)],
            'bank_name' => ['required', 'string', 'max:100'],
            'account_name' => ['required', 'string', 'max:150'],
            'account_number' => ['required', 'string', 'max:100'],
            'swift_code' => ['nullable', 'string', 'max:20'],
            'bank_address' => ['nullable', 'string', 'max:255'],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'qr' => ['nullable', ...PrivateFiles::imageRules()],
            'remove_qr' => ['boolean'],
        ]);

        return [
            ...collect($validated)->except(['qr', 'remove_qr'])->all(),
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
