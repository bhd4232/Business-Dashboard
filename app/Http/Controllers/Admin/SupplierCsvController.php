<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Services\SupplierCsvService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupplierCsvController extends Controller
{
    public function export(Request $request, SupplierCsvService $suppliers): StreamedResponse
    {
        abort_unless($request->user()?->canPerformModelAbility('viewAny', Supplier::class), 403);

        return $suppliers->export();
    }

    public function sample(Request $request, SupplierCsvService $suppliers): StreamedResponse
    {
        abort_unless($request->user()?->canPerformModelAbility('viewAny', Supplier::class), 403);

        return $suppliers->sample();
    }
}
