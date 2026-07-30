<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LegacyAdminClusterRedirectController extends Controller
{
    /**
     * Legacy top-level Filament paths mapped to their native cluster slug.
     *
     * @var array<string, string>
     */
    private const CLUSTERS = [
        'storefront-slides' => 'storefront',
        'storefront-settings' => 'storefront',
        'storefront-pages' => 'storefront',
        'product-carousels' => 'storefront',
        'storefront-payments' => 'storefront',
        'leads' => 'crm',
        'quotations' => 'crm',
        'inbox' => 'crm',
        'conversation-channels' => 'crm',
        'ai-assistant-settings' => 'crm',
        'company-faqs' => 'crm',
        'vouchers' => 'finance',
        'fund-sources' => 'finance',
        'fund-transfers' => 'finance',
        'accounts' => 'finance',
        'customers' => 'sales',
        'orders' => 'sales',
        'customer-payments' => 'sales',
        'suppliers' => 'purchasing',
        'purchases' => 'purchasing',
        'supplier-payments' => 'purchasing',
        'products' => 'inventory',
        'stock-movements' => 'inventory',
        'categories' => 'inventory',
        'expenses' => 'finance',
        'expense-categories' => 'finance',
        'transaction-ledgers' => 'finance',
        'users' => 'settings',
        'user-roles' => 'settings',
        'product-setup' => 'settings',
        'audit-logs' => 'settings',
        'backups' => 'settings',
        'cloud-storage-settings' => 'settings',
        'release-notes' => 'settings',
    ];

    /**
     * @return list<string>
     */
    public static function legacySegments(): array
    {
        return array_keys(self::CLUSTERS);
    }

    public function __invoke(Request $request, string $legacy, ?string $path = null): RedirectResponse
    {
        $cluster = self::CLUSTERS[$legacy] ?? abort(404);

        if ($legacy === 'fund-sources') {
            return $this->redirectWithQuery($request, '/admin/finance/accounts');
        }

        if ($legacy === 'fund-transfers') {
            return $this->redirectWithQuery($request, '/admin/finance/vouchers');
        }

        $suffix = filled($path) ? '/'.ltrim($path, '/') : '';
        $target = "/admin/{$cluster}/{$legacy}{$suffix}";

        return $this->redirectWithQuery($request, $target);
    }

    protected function redirectWithQuery(Request $request, string $target): RedirectResponse
    {
        if (filled($query = $request->getQueryString())) {
            $target .= "?{$query}";
        }

        return redirect()->to($target);
    }
}
