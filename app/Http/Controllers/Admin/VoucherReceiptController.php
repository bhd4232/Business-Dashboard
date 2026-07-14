<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Voucher;
use App\Services\CompanySettingsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shareable Money Receipt for an approved credit voucher. Reached via a
 * Laravel signed URL (no login required, but the signature can't be
 * guessed) so a customer can open it from a WhatsApp/SMS link.
 */
class VoucherReceiptController extends Controller
{
    public function __invoke(Voucher $voucher, CompanySettingsService $settings): Response
    {
        abort_unless($voucher->isCredit() && $voucher->status === Voucher::STATUS_APPROVED, 404);

        return Pdf::loadView('vouchers.receipt', [
            'voucher' => $voucher->load(['customer', 'supplier']),
            'company' => $settings->profile($voucher->company),
        ])->setPaper('a5')->stream($voucher->voucher_number.'.pdf');
    }
}
