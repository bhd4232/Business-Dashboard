<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\ProductCsvService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductCsvController extends Controller
{
    public function export(Request $request, ProductCsvService $products): StreamedResponse
    {
        abort_unless($request->user()?->canPerformModelAbility('viewAny', Product::class), 403);

        return $products->export();
    }

    public function sample(Request $request, ProductCsvService $products): StreamedResponse
    {
        abort_unless($request->user()?->canPerformModelAbility('viewAny', Product::class), 403);

        return $products->sample();
    }
}
