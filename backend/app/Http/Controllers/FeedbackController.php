<?php

namespace App\Http\Controllers;

use App\Models\Feedback;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The in-app feedback box: any logged-in learner can drop a note from the
 * dashboard; admins read and clear them in the panel. A private channel so
 * criticism reaches the maker before it reaches Reddit.
 */
class FeedbackController extends Controller
{
    /** POST /api/feedback - store a note from the dashboard widget. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        Feedback::create([
            'user_id' => $request->user()->id,
            'message' => trim($data['message']),
            'created_at' => now(),
        ]);

        return response()->json(['ok' => true], 201);
    }

    /** GET /api/admin/feedback - newest first, with who said it. */
    public function index(): JsonResponse
    {
        $items = Feedback::with('user:id,name,email')
            ->orderByDesc('id')
            ->paginate(25);

        return response()->json($items);
    }

    /** DELETE /api/admin/feedback/{feedback} - handled, clear it out. */
    public function destroy(Feedback $feedback): JsonResponse
    {
        $feedback->delete();

        return response()->json(['ok' => true]);
    }
}
