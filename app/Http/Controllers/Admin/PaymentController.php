<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\PaymentReviewed;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Bank transfers waiting for review. Approving one extends the payer's Premium by the plan's length, starting from
 * today or from the end of the Premium they already have.
 */
class PaymentController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->query('status', Payment::PENDING);
        $status = in_array($status, [...Payment::STATUSES, 'all'], true) ? $status : Payment::PENDING;
        $search = Str::limit(trim((string) $request->query('search', '')), 100, '');

        $payments = Payment::query()
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search) {
                $like = '%'.addcslashes(Str::lower($search), '%_\\').'%';
                $query->where(fn ($query) => $query
                    ->whereRaw('lower(payer_name) like ?', [$like])
                    ->orWhereRaw('lower(payer_email) like ?', [$like])
                    ->orWhereRaw('lower(reference_number) like ?', [$like]));
            })
            // Oldest first while waiting, so the queue is worked in order; newest first otherwise
            ->when($status === Payment::PENDING, fn ($query) => $query->oldest()->oldest('id'), fn ($query) => $query->latest()->latest('id'))
            ->with('reviewer:id,name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Payment $payment) => [
                'id' => $payment->id,
                'user_id' => $payment->user_id,
                'payer_name' => $payment->payer_name,
                'payer_email' => $payment->payer_email,
                'plan_name' => $payment->plan_name,
                'duration_months' => $payment->duration_months,
                'paid_to' => $payment->paid_to,
                'currency' => $payment->currency,
                'amount' => $payment->amount,
                'reference_number' => $payment->reference_number,
                'paid_on' => $payment->paid_on?->toDateString(),
                'payer_note' => $payment->payer_note,
                'receipt_mime' => $payment->receipt_mime,
                'status' => $payment->status,
                'review_note' => $payment->review_note,
                'reviewer' => $payment->reviewer?->name,
                'reviewed_at' => $payment->reviewed_at?->toIso8601String(),
                'premium_until' => $payment->premium_until?->toIso8601String(),
                'created_at' => $payment->created_at->toIso8601String(),
            ]);

        return Inertia::render('admin/Payments', [
            'payments' => $payments,
            'filters' => ['status' => $status, 'search' => $search],
            'counts' => Payment::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function approve(Request $request, Payment $payment): RedirectResponse
    {
        $payment = DB::transaction(function () use ($request, $payment) {
            $payment = Payment::lockForUpdate()->findOrFail($payment->id);
            $this->ensurePending($payment);

            $user = $payment->user_id ? User::lockForUpdate()->find($payment->user_id) : null;
            $from = $user?->isPremium() ? $user->premium_until->copy() : now();
            $until = $from->copy()->addMonthsNoOverflow($payment->duration_months);

            if ($user) {
                $user->forceFill(['premium_until' => $until])->save();
            }

            $payment->forceFill([
                'status' => Payment::APPROVED,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'review_note' => null,
                'premium_from' => $user ? $from : null,
                'premium_until' => $user ? $until : null,
            ])->save();

            return $payment;
        });

        $this->notifyPayer($payment);

        return back();
    }

    public function reject(Request $request, Payment $payment): RedirectResponse
    {
        $validated = $request->validate([
            'review_note' => ['required', 'string', 'max:1000'],
        ], [
            'review_note.required' => 'Tell the payer why, e.g. "No transfer with this reference arrived".',
        ]);

        $payment = DB::transaction(function () use ($request, $payment, $validated) {
            $payment = Payment::lockForUpdate()->findOrFail($payment->id);
            $this->ensurePending($payment);

            $payment->forceFill([
                'status' => Payment::REJECTED,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'review_note' => trim($validated['review_note']),
            ])->save();

            return $payment;
        });

        $this->notifyPayer($payment);

        return back();
    }

    private function ensurePending(Payment $payment): void
    {
        if (! $payment->isPending()) {
            throw ValidationException::withMessages([
                'payment' => 'This payment was already '.$payment->status.'.',
            ]);
        }
    }

    /**
     * Email the payer. A mail failure is logged, not shown: the review itself is saved, and the payer also sees the
     * result on their billing page.
     */
    private function notifyPayer(Payment $payment): void
    {
        $user = $payment->user_id ? User::find($payment->user_id) : null;

        if (! $user) {
            return;
        }

        try {
            $user->notify(new PaymentReviewed($payment));
        } catch (Throwable $e) {
            Log::warning('Could not email a payment review', ['payment' => $payment->id, 'message' => $e->getMessage()]);
        }
    }
}
