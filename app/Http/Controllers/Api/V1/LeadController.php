<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Website inquiry submission -> ERP CRM "query list". Lands on the existing
 * Lead model with source='website' (already one of Lead::SOURCES), so no
 * new CRM concept was needed for this.
 */
class LeadController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'interest' => ['nullable', 'string', 'max:2000'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $lead = Lead::query()->create([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'] ?? null,
            'source' => 'website',
            'status' => 'new',
            'interest' => $data['interest'] ?? null,
            'note' => $data['note'] ?? null,
        ]);

        return response()->json([
            'data' => [
                'id' => $lead->getKey(),
                'status' => $lead->status,
                'created_at' => $lead->created_at?->toIso8601String(),
            ],
        ], 201);
    }
}
