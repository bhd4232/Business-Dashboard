<?php

namespace App\Http\Controllers;

use App\Models\Quotation;
use Illuminate\View\View;

class QuotationPublicController extends Controller
{
    public function show(string $quotationNumber): View
    {
        $quotation = Quotation::query()
            ->withoutGlobalScopes()
            ->where('quotation_number', $quotationNumber)
            ->with(['items.product', 'items.productVariant', 'company', 'customer', 'lead'])
            ->firstOrFail();

        return view('quotations.public', compact('quotation'));
    }
}
