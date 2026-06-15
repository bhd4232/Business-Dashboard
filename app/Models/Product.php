<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Product extends Model
{
    public const STATUS_AVAILABLE = 'available';

    public const STATUS_COMING_SOON = 'coming_soon';

    public const STATUSES = [
        self::STATUS_AVAILABLE => 'Available',
        self::STATUS_COMING_SOON => 'Coming Soon',
    ];

    public const COMING_SOON_PURCHASE_PRODUCTS = Purchase::CHINA_TO_BD_COST_FIELDS;

    protected $fillable = [
        'name',
        'description',
        'sku',
        'barcode',
        'unit',
        'brand',
        'cost_price',
        'sale_price',
        'price',
        'stock',
        'reorder_level',
        'vat_rate',
        'is_active',
        'status',
        'image',
        'category_id',
    ];

    protected $casts = [
        'cost_price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'price' => 'decimal:2',
        'vat_rate' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function purchaseItems()
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function getSellingPriceAttribute(): float
    {
        return (float) ($this->sale_price ?? $this->price ?? 0);
    }

    public static function ensureComingSoonPurchaseProducts(): void
    {
        foreach (self::COMING_SOON_PURCHASE_PRODUCTS as $name) {
            $product = self::query()->firstOrNew(['name' => $name]);

            $product->fill([
                'sku' => $product->sku ?: self::comingSoonSku($name),
                'price' => $product->price ?? 0,
                'sale_price' => $product->sale_price ?? 0,
                'cost_price' => $product->cost_price ?? 0,
                'stock' => $product->stock ?? 0,
                'unit' => $product->unit ?: 'pcs',
                'reorder_level' => $product->reorder_level ?? 0,
                'vat_rate' => $product->vat_rate ?? 0,
                'is_active' => false,
                'status' => self::STATUS_COMING_SOON,
            ]);

            $product->save();
        }
    }

    protected static function comingSoonSku(string $name): string
    {
        $baseSku = 'CS-'.Str::upper(Str::slug($name));
        $sku = $baseSku;
        $suffix = 2;

        while (self::query()->where('sku', $sku)->exists()) {
            $sku = "{$baseSku}-{$suffix}";
            $suffix++;
        }

        return $sku;
    }

    public function setStockFromProductForm(int $targetStock): void
    {
        $this->refresh();

        $currentStock = (int) $this->stock;

        if ($targetStock === $currentStock) {
            return;
        }

        if ($currentStock === 0 && $targetStock > 0 && ! $this->stockMovements()->exists()) {
            StockMovement::query()->create([
                'product_id' => $this->getKey(),
                'type' => 'opening',
                'quantity' => $targetStock,
                'reference_type' => self::class,
                'reference_id' => $this->getKey(),
                'reason' => 'Opening stock setup',
                'note' => 'Opening stock from product form',
            ]);

            return;
        }

        StockMovement::query()->create([
            'product_id' => $this->getKey(),
            'type' => 'adjustment',
            'quantity' => $targetStock - $currentStock,
            'reference_type' => self::class,
            'reference_id' => $this->getKey(),
            'reason' => 'Product form stock correction',
            'note' => 'Stock adjustment from product form',
        ]);
    }

    public function isLowStock(): bool
    {
        return $this->stock <= $this->reorder_level;
    }
}
