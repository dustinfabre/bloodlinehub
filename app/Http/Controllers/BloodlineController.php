<?php

namespace App\Http\Controllers;

use App\Models\Bloodline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BloodlineController extends Controller
{
    /**
     * List all bloodlines for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $bloodlines = Bloodline::where('user_id', $request->user()->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json($bloodlines);
    }

    /**
     * Search bloodlines by name.
     */
    public function search(Request $request): JsonResponse
    {
        $query = $request->input('q', '');
        $user = $request->user();

        $bloodlines = Bloodline::where('user_id', $user->id)
            ->when($query, function ($q) use ($query) {
                $likeOp = config('database.default') === 'pgsql' ? 'ilike' : 'like';
                $q->where('name', $likeOp, "%{$query}%");
            })
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name']);

        return response()->json($bloodlines);
    }

    /**
     * Store a new bloodline.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('bloodlines')->where(function ($query) use ($request) {
                    return $query->where('user_id', $request->user()->id)
                        ->whereRaw('UPPER(name) = ?', [strtoupper($request->name)]);
                }),
            ],
        ], [
            'name.unique' => 'You already have a bloodline with this name.',
        ]);

        $bloodline = Bloodline::create([
            'user_id' => $request->user()->id,
            'name' => strtoupper($validated['name']),
        ]);

        return response()->json($bloodline, 201);
    }

    /**
     * Update a bloodline.
     */
    public function update(Request $request, Bloodline $bloodline): JsonResponse
    {
        // Ensure user owns this bloodline
        if ($bloodline->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('bloodlines')->where(function ($query) use ($request, $bloodline) {
                    return $query->where('user_id', $request->user()->id)
                        ->whereRaw('UPPER(name) = ?', [strtoupper($request->name)])
                        ->where('id', '!=', $bloodline->id);
                }),
            ],
        ], [
            'name.unique' => 'You already have a bloodline with this name.',
        ]);

        $bloodline->update([
            'name' => strtoupper($validated['name']),
        ]);

        return response()->json($bloodline);
    }

    /**
     * Delete a bloodline.
     */
    public function destroy(Request $request, Bloodline $bloodline): JsonResponse
    {
        // Ensure user owns this bloodline
        if ($bloodline->user_id !== $request->user()->id) {
            abort(403);
        }

        // Check if bloodline is in use
        $pigeonCount = $bloodline->pigeons()->count();
        
        if ($pigeonCount > 0) {
            return response()->json([
                'message' => "Cannot delete bloodline. It is assigned to {$pigeonCount} pigeon(s).",
                'pigeon_count' => $pigeonCount,
            ], 422);
        }

        $bloodline->delete();

        return response()->json(['message' => 'Bloodline deleted successfully.']);
    }

    /**
     * Get or create a bloodline by name.
     * Used for quick creation when typing new bloodline names.
     */
    public function getOrCreate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $name = strtoupper($validated['name']);
        $user = $request->user();

        $bloodline = Bloodline::firstOrCreate(
            [
                'user_id' => $user->id,
                'name' => $name,
            ]
        );

        return response()->json($bloodline, $bloodline->wasRecentlyCreated ? 201 : 200);
    }
}
