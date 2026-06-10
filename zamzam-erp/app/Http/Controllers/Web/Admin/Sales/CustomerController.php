<?php

namespace App\Http\Controllers\Web\Admin\Sales;

use App\Http\Controllers\Controller;
use App\Models\Sales\Customer;
use App\Models\Sales\CustomerTag;
use App\Models\Sales\PriceTier;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('customers.view');

        $customers = Customer::with(['tags', 'priceTier', 'assignedSalesman'])
            ->when($request->search, fn($q, $s) =>
                $q->where(fn($q) =>
                    $q->where('name', 'like', "%{$s}%")
                      ->orWhere('phone', 'like', "%{$s}%")
                      ->orWhere('customer_code', 'like', "%{$s}%")
                      ->orWhere('external_id', 'like', "%{$s}%")
                ))
            ->when($request->type, fn($q, $t) => $q->where('type', $t))
            ->when($request->tag_id, fn($q, $tid) => $q->whereHas('tags', fn($q) => $q->where('customer_tags.id', $tid)))
            ->when($request->price_tier_id, fn($q, $pid) => $q->where('price_tier_id', $pid))
            ->when($request->filled('is_active'), fn($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Sales/Customers/Index', [
            'customers'  => $customers,
            'tags'       => CustomerTag::active()->orderBy('sort_order')->get(['id','name','color']),
            'priceTiers' => PriceTier::active()->get(['id','name']),
            'filters'    => $request->only(['search','type','tag_id','price_tier_id','is_active']),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('customers.create');

        return Inertia::render('Sales/Customers/Create', [
            'tags'       => CustomerTag::active()->orderBy('sort_order')->get(['id','name','color']),
            'priceTiers' => PriceTier::active()->get(['id','name']),
            'salesmen'   => User::where('is_active', true)->get(['id','name']),
        ]);
    }

    public function show(Customer $customer): Response
    {
        $this->authorize('customers.view');

        return Inertia::render('Sales/Customers/Show', [
            'customer' => $customer->load([
                'tags',
                'priceTier',
                'assignedSalesman',
                'salesOrders' => fn($q) => $q->latest()->limit(10),
            ]),
        ]);
    }

    public function edit(Customer $customer): Response
    {
        $this->authorize('customers.edit');

        return Inertia::render('Sales/Customers/Edit', [
            'record'     => $customer->load('tags'),
            'tags'       => CustomerTag::active()->orderBy('sort_order')->get(['id','name','color']),
            'priceTiers' => PriceTier::active()->get(['id','name']),
            'salesmen'   => User::where('is_active', true)->get(['id','name']),
        ]);
    }
}
