<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FeedbackLabel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The labels admins use to group feedback in the inbox.
 */
class FeedbackLabelController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        FeedbackLabel::create($this->validated($request));

        return back();
    }

    public function update(Request $request, FeedbackLabel $label): RedirectResponse
    {
        $label->update($this->validated($request, $label));

        return back();
    }

    /**
     * Taking a label away from its messages; the messages stay.
     */
    public function destroy(FeedbackLabel $label): RedirectResponse
    {
        $label->delete();

        return back();
    }

    /**
     * @return array{name: string, color: string}
     */
    private function validated(Request $request, ?FeedbackLabel $label = null): array
    {
        $request->merge(['name' => trim((string) $request->input('name'))]);

        return $request->validate([
            'name' => ['required', 'string', 'max:40', Rule::unique('feedback_labels', 'name')->ignore($label)],
            'color' => ['required', Rule::in(FeedbackLabel::COLORS)],
        ], [
            'name.unique' => 'There is already a label with that name.',
        ]);
    }
}
