<?php

namespace App\Http\Controllers;

use App\Models\Pigeon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        
        $totalPigeons = Pigeon::where('user_id', $user->id)->count();
        $recentPigeons = Pigeon::where('user_id', $user->id)
            ->latest()
            ->take(5)
            ->get(['id', 'ring_number', 'personal_number', 'color', 'created_at']);
        
        return Inertia::render('Dashboard', [
            'stats' => [
                'total_pigeons' => $totalPigeons,
                'recent_pigeons' => $recentPigeons,
            ],
        ]);
    }
}
