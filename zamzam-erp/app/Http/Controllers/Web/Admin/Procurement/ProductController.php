<?php

namespace App\Http\Controllers\Web\Admin\Procurement;

use App\Http\Controllers\Controller;
use App\Models\Core\Category;
use App\Models\Core\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(Request $request): Response
    {
        $products = Product::with(['category'])
            ->when($request->search, fn($q, $s) =>
                $q->where(fn($q) =>
                    $q->where('name', 'like', "%{$s}%")
                      ->orWhere('sku', 'like', "%{$s}%")
                ))
            ->when($request->category_id, fn($q, $c) => $q->where('category_id', $c))
            ->when($request->boolean('active_only', true), fn($q) => $q->active())
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        $categories = Category::active()->root()->with('children')->orderBy('sort_order')->get();

        return Inertia::render('Procurement/Products/Index', [
            'products'   => $products,
            'categories' => $categories,
            'filters'    => $request->only(['search', 'category_id', 'active_only']),
        ]);
    }

    public function create(): Response
    {
        $categories = Category::active()->root()->with('children')->orderBy('sort_order')->get();

        return Inertia::render('Procurement/Products/Create', [
            'categories' => $categories,
        ]);
    }

    public function show(Product $product): Response
    {
        return Inertia::render('Procurement/Products/Show', [
            'product' => $product->load(['category', 'variants', 'barcodes']),
        ]);
    }

    public function edit(Product $product): Response
    {
        $categories = Category::active()->root()->with('children')->orderBy('sort_order')->get();

        return Inertia::render('Procurement/Products/Edit', [
            'product'    => $product->load(['category', 'variants']),
            'categories' => $categories,
        ]);
    }

    public function categories(Request $request): Response
    {
        $categories = Category::with('parent', 'children')
            ->withCount('products')
            ->orderBy('sort_order')
            ->get();

        return Inertia::render('Procurement/Categories/Index', [
            'categories' => $categories,
        ]);
    }
}
