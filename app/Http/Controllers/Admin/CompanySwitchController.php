<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CompanySwitchController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();
        $companyId = $request->input('company_id');

        if ($user?->isSuperAdmin() && $companyId === 'all') {
            $request->session()->put('current_company_id', 'all');
            $request->session()->put('current_company_selection_explicit', true);

            return back();
        }

        if (is_numeric($companyId) && $user?->canAccessCompany((int) $companyId)) {
            $request->session()->put('current_company_id', (int) $companyId);
            $request->session()->put('current_company_selection_explicit', true);
        }

        return back();
    }
}
