<?php

namespace App\Http\Controllers;

use App\Models\MobileCrashReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Receives crash reports the Android app saves locally when it crashes and
 * uploads on its next successful launch (see CrashReporter.java). Public and
 * unauthenticated on purpose: a crash can happen before login, so there is
 * no session/user to attach it to.
 */
class MobileCrashReportController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'exception_class' => ['required', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:2000'],
            'stack_trace' => ['required', 'string', 'max:20000'],
            'app_version_name' => ['nullable', 'string', 'max:64'],
            'app_version_code' => ['nullable', 'integer', 'min:0'],
            'os_version' => ['nullable', 'string', 'max:64'],
            'device_manufacturer' => ['nullable', 'string', 'max:64'],
            'device_model' => ['nullable', 'string', 'max:64'],
            'occurred_at' => ['nullable', 'date'],
        ]);

        MobileCrashReport::query()->create($data + [
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['status' => 'received'], 201);
    }
}
