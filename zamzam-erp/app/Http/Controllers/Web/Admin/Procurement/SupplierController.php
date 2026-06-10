<?php

namespace App\Http\Controllers\Web\Admin\Procurement;

use App\Http\Controllers\Controller;
use App\Models\Core\Category;
use App\Models\Core\Product;
use App\Models\Procurement\Supplier;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('suppliers.view');

        $suppliers = Supplier::with('primaryContact')
            ->when($request->search, fn($q, $s) =>
                $q->where(fn($q) =>
                    $q->where('name_english', 'like', "%{$s}%")
                      ->orWhere('name_chinese', 'like', "%{$s}%")
                ))
            ->when($request->boolean('active_only', true), fn($q) => $q->active())
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Procurement/Suppliers/Index', [
            'suppliers' => $suppliers,
            'filters'   => $request->only(['search', 'active_only']),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('suppliers.create');

        return Inertia::render('Procurement/Suppliers/Create');
    }

    public function show(Supplier $supplier): Response
    {
        $this->authorize('suppliers.view');

        return Inertia::render('Procurement/Suppliers/Show', [
            'supplier' => $supplier->load(['contacts', 'purchaseOrders' => fn($q) => $q->latest()->limit(10)]),
        ]);
    }

    public function edit(Supplier $supplier): Response
    {
        $this->authorize('suppliers.edit');

        return Inertia::render('Procurement/Suppliers/Edit', [
            'supplier' => $supplier->load('contacts'),
        ]);
    }
}
