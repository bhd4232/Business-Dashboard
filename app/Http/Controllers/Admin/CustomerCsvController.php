<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\CustomerCsvService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerCsvController extends Controller
{
    public function export(Request $request, CustomerCsvService $customers): StreamedResponse
    {
        abort_unless($request->user()?->canPerformModelAbility('viewAny', Customer::class), 403);

        return $customers->export();
    }

    public function sample(Request $request, CustomerCsvService $customers): StreamedResponse
    {
        abort_unless($request->user()?->canPerformModelAbility('viewAny', Customer::class), 403);

        return $customers->sample();
    }
}
