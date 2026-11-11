<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Feedback;
use App\Models\InviteRequest;
use App\Models\Payment;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin overview: sign-ups, Premium, payments waiting for review, unread feedback and the old invite list.
 */
class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $approved = Payment::where('status', Payment::APPROVED);

        return Inertia::render('admin/Dashboard', [
            'stats' => [
                'users' => User::count(),
                'users_7d' => User::where('created_at', '>=', now()->subDays(7))->count(),
                'users_30d' => User::where('created_at', '>=', now()->subDays(30))->count(),
                'premium' => User::where('premium_until', '>', now())->count(),
                // On the free trial and not (yet) paying
                'trial' => User::where('trial_ends_at', '>', now())
                    ->where(fn ($query) => $query->whereNull('premium_until')->orWhere('premium_until', '<=', now()))
                    ->count(),
                'pending_payments' => Payment::pending()->count(),
                'unread_feedback' => Feedback::unread()->count(),
                // Approved in the last 30 days, per currency
                'revenue_30d' => (clone $approved)->where('reviewed_at', '>=', now()->subDays(30))
                    ->selectRaw('currency, sum(amount) as total')->groupBy('currency')
                    ->pluck('total', 'currency'),
            ],
            'pendingPayments' => Payment::pending()->oldest()->take(5)
                ->get(['id', 'payer_name', 'plan_name', 'currency', 'amount', 'created_at']),
            'recentUsers' => User::latest()->latest('id')->take(8)
                ->get(['id', 'name', 'email', 'premium_until', 'trial_ends_at', 'created_at']),
            // Emails left on the "request an invite" page before sign-up opened, to tell them it's open
            'waitlist' => InviteRequest::latest()->pluck('email'),
        ]);
    }
}
