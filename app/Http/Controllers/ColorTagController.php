<?php

namespace App\Http\Controllers;

use App\Models\ColorTag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ColorTagController extends Controller
{
    /**
     * List all color tags for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $colorTags = ColorTag::where('user_id', $request->user()->id)
            ->orderBy('name')
            ->get(['id', 'name', 'color']);

        return response()->json($colorTags);
    }

    /**
     * Store a new color tag.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('color_tags')->where(function ($query) use ($request) {
                    return $query->where('user_id', $request->user()->id);
                }),
            ],
            'color' => [
                'required',
                'string',
                'regex:/^#[0-9A-Fa-f]{6}$/',
            ],
        ], [
            'name.unique' => 'You already have a color tag with this name.',
            'color.regex' => 'Color must be a valid hex color (e.g., #FF5733).',
        ]);

        $colorTag = ColorTag::create([
            'user_id' => $request->user()->id,
            'name' => $validated['name'],
            'color' => strtoupper($validated['color']),
        ]);

        return response()->json($colorTag, 201);
    }

    /**
     * Update a color tag.
     */
    public function update(Request $request, ColorTag $colorTag): JsonResponse
    {
        // Ensure user owns this color tag
        if ($colorTag->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('color_tags')->where(function ($query) use ($request) {
                    return $query->where('user_id', $request->user()->id);
                })->ignore($colorTag->id),
            ],
            'color' => [
                'required',
                'string',
                'regex:/^#[0-9A-Fa-f]{6}$/',
            ],
        ], [
            'name.unique' => 'You already have a color tag with this name.',
            'color.regex' => 'Color must be a valid hex color (e.g., #FF5733).',
        ]);

        $colorTag->update([
            'name' => $validated['name'],
            'color' => strtoupper($validated['color']),
        ]);

        return response()->json($colorTag);
    }

    /**
     * Delete a color tag.
     * Pigeons using this tag will have their color_tag_id set to null (via DB constraint).
     */
    public function destroy(Request $request, ColorTag $colorTag): JsonResponse
    {
        // Ensure user owns this color tag
        if ($colorTag->user_id !== $request->user()->id) {
            abort(403);
        }

        // Get count of pigeons using this tag for info
        $pigeonCount = $colorTag->pigeons()->count();

        $colorTag->delete();

        return response()->json([
            'message' => 'Color tag deleted successfully.',
            'pigeons_affected' => $pigeonCount,
        ]);
    }
}
