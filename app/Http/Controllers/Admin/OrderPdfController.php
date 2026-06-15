<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\CompanySettingsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OrderPdfController extends Controller
{
    public function __invoke(Order $order, Request $request, CompanySettingsService $settings): Response
    {
        abort_unless($request->user()?->canPerformModelAbility('view', Order::class), 403);

        $order->load(['customer', 'items.product']);

        return Pdf::loadView('orders.pdf', [
            'order' => $order,
            'company' => $settings->profile(),
        ])->setPaper('a4')->download($order->order_number.'.pdf');
    }
}
