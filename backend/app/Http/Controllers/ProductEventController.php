<?php

namespace App\Http\Controllers;

use App\Models\ProductEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Accept the few exposure/click milestones that only the browser can see. */
class ProductEventController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'event' => ['required', 'string', Rule::in(ProductEvent::CLIENT_EVENTS)],
        ]);

        ProductEvent::record($request->user(), $data['event']);

        return response()->json([], 204);
    }
}
