<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Category;
use App\Models\Company;
use App\Models\CourierProvider;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductCarousel;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\StockMovement;
use App\Models\StorefrontPage;
use App\Models\StorefrontSetting;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Services\CompanyContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $today = now()->toDateString();
        $company = Company::defaultCompany();
        Company::seedCoreCompanies();
        app(CompanyContext::class)->set($company);

        if ($company) {
            StorefrontSetting::query()->updateOrCreate(
                ['company_id' => $company->getKey()],
                [
                    'theme_color' => '#F59E0B',
                    'whatsapp_number' => '+8801700000000',
                    'meta_title' => 'ZamZam Demo Store',
                    'meta_description' => 'Demo storefront powered by ZamZam ERP products and inventory.',
                    'is_published' => true,
                ],
            );
        }

        $demoUser = User::query()->updateOrCreate(
            ['email' => 'demo@example.com'],
            [
                'name' => 'Demo Admin',
                'password' => 'password',
                'role' => 'super_admin',
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        if ($company) {
            $demoUser->companies()->syncWithoutDetaching([
                $company->getKey() => [
                    'role' => 'owner',
                    'is_default' => true,
                ],
            ]);
        }

        $accounts = collect([
            ['Demo Cash 01', 'cash', 800000],
            ['Demo Cash 02', 'cash', 650000],
            ['Demo Bank 01', 'bank', 1200000],
            ['Demo Bank 02', 'bank', 950000],
            ['Demo Mobile Banking 01', 'mobile_banking', 300000],
            ['Demo Mobile Banking 02', 'mobile_banking', 275000],
            ['Demo Petty Cash', 'cash', 150000],
            ['Demo Import Bank', 'bank', 1600000],
            ['Demo Sales Counter Cash', 'cash', 450000],
            ['Demo Online Payment Wallet', 'mobile_banking', 225000],
        ])->map(fn (array $account): Account => Account::query()->updateOrCreate(
            ['name' => $account[0]],
            [
                'type' => $account[1],
                'opening_balance' => $account[2],
                'is_active' => true,
            ],
        ));

        $categoryNames = [
            'Electronics',
            'Appliances',
            'Mobile Accessories',
            'Networking',
            'Computer Accessories',
            'Security Devices',
            'Audio Devices',
            'Office Equipment',
            'Home Gadgets',
            'Spare Parts',
        ];

        $categories = collect($categoryNames)
            ->mapWithKeys(fn (string $name): array => [$name => $this->category($name, "Demo {$name} products.")]);

        $productRows = [
            ['Mercury Dual Band Router', 'DEMO-ROUTER-001', 'Networking', 'Mercury', 1450, 2200, 8],
            ['ZamZam Bluetooth Speaker', 'DEMO-SPEAKER-001', 'Audio Devices', 'ZamZam', 900, 1550, 10],
            ['Smart Electric Kettle', 'DEMO-KETTLE-001', 'Appliances', 'SmartHome', 780, 1250, 6],
            ['Type-C Fast Charging Cable', 'DEMO-CABLE-001', 'Mobile Accessories', 'PowerLine', 120, 250, 20],
            ['HD CCTV Camera', 'DEMO-CCTV-001', 'Security Devices', 'SecureCam', 1850, 2950, 5],
            ['Wireless Keyboard Mouse Combo', 'DEMO-KB-MOUSE-001', 'Computer Accessories', 'KeyPro', 950, 1650, 8],
            ['Portable Mini UPS', 'DEMO-MINI-UPS-001', 'Electronics', 'VoltMax', 1350, 2350, 6],
            ['Barcode Scanner', 'DEMO-SCANNER-001', 'Office Equipment', 'ScanFast', 2100, 3400, 4],
            ['Smart LED Bulb', 'DEMO-LED-BULB-001', 'Home Gadgets', 'Brightly', 220, 420, 25],
            ['Router Power Adapter', 'DEMO-ADAPTER-001', 'Spare Parts', 'PowerLine', 160, 320, 18],
        ];

        $products = collect($productRows)->map(function (array $row, int $index) use ($categories): Product {
            $product = $this->product($categories[$row[2]], [
                'name' => $row[0],
                'sku' => $row[1],
                'barcode' => '8801000000'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
                'brand' => $row[3],
                'cost_price' => $row[4],
                'sale_price' => $row[5],
                'price' => $row[5],
                'reorder_level' => $row[6],
            ]);

            $this->openingStock($product, 100 + ($index * 5));
            $this->attachProductImages($product, $index);

            return $product;
        })->values();

        $this->seedVariableProducts($products);
        $this->seedCarousels($products);
        $this->seedStorefrontPages();
        $this->seedCourierProviders();

        $supplierRows = [
            ['Li Wei', 'Shenzhen Demo Trading Co.', '+8613800138000', 'sales@shenzhen-demo.example', 'Huaqiangbei, Shenzhen, China'],
            ['Rahim Supplier', 'Dhaka Wholesale Mart', '+8801811000000', 'support@dhaka-wholesale.example', 'Mogbazar, Dhaka'],
            ['Chen Import', 'Guangzhou Parts Hub', '+8613900139001', 'chen@parts-demo.example', 'Guangzhou, China'],
            ['Karim Electronics', 'Karim Electronics Supply', '+8801811000003', 'karim@electronics-demo.example', 'Chawk Bazar, Dhaka'],
            ['Nusrat Telecom', 'Nusrat Telecom Distribution', '+8801811000004', 'nusrat@telecom-demo.example', 'GEC, Chattogram'],
            ['Hasan Gadget', 'Hasan Gadget House', '+8801811000005', 'hasan@gadget-demo.example', 'New Market, Dhaka'],
            ['Wang Logistics', 'Wang Logistics Support', '+8613600136006', 'wang@logistics-demo.example', 'Yiwu, China'],
            ['Mim Office', 'Mim Office Equipment', '+8801811000007', 'mim@office-demo.example', 'Motijheel, Dhaka'],
            ['Salma Security', 'Salma Security Devices', '+8801811000008', 'salma@security-demo.example', 'Agrabad, Chattogram'],
            ['Rafi Spare', 'Rafi Spare Parts', '+8801811000009', 'rafi@spare-demo.example', 'Elephant Road, Dhaka'],
        ];

        $suppliers = collect($supplierRows)->map(fn (array $row, int $index): Supplier => Supplier::query()->updateOrCreate(
            ['email' => $row[3]],
            [
                'name' => $row[0],
                'company_name' => $row[1],
                'phone' => $row[2],
                'address' => $row[4],
                'opening_balance' => $index * 500,
                'is_active' => true,
            ],
        ))->values();

        $customerRows = [
            ['Farhan Retail Store', '+8801712345678', 'farhan.retail@example.com', 'Mirpur, Dhaka', 'retail', 'facebook'],
            ['North Star Office Supplies', '+8801911223344', 'procurement@northstar.example', 'Agrabad, Chattogram', Customer::typeKey('Corporate Client'), Customer::sourceKey('Trade Fair')],
            ['Sadia Online Shop', '+8801712000002', 'sadia@shop-demo.example', 'Uttara, Dhaka', 'regular', 'website'],
            ['Rasel Wholesale', '+8801712000003', 'rasel@wholesale-demo.example', 'Khatunganj, Chattogram', 'wholesale', 'referral'],
            ['Mahi Gadget Corner', '+8801712000004', 'mahi@gadget-demo.example', 'Sylhet Sadar', 'retail', 'walk_in'],
            ['Nabil Enterprise', '+8801712000005', 'nabil@enterprise-demo.example', 'Bogura', Customer::typeKey('Dealer'), Customer::sourceKey('WhatsApp Lead')],
            ['Tania Corporate', '+8801712000006', 'tania@corporate-demo.example', 'Banani, Dhaka', Customer::typeKey('Corporate Client'), 'phone_call'],
            ['Ovi Telecom', '+8801712000007', 'ovi@telecom-demo.example', 'Rajshahi', 'regular', 'facebook'],
            ['Rupa IT Zone', '+8801712000008', 'rupa@itzone-demo.example', 'Khulna', 'vip', 'website'],
            ['Jamal Retail Point', '+8801712000009', 'jamal@retail-demo.example', 'Barishal', 'retail', 'other'],
        ];

        $customers = collect($customerRows)->map(fn (array $row, int $index): Customer => Customer::query()->updateOrCreate(
            ['email' => $row[2]],
            [
                'name' => $row[0],
                'phone' => $row[1],
                'address' => $row[3],
                'customer_type' => $row[4],
                'customer_source' => $row[5],
                'opening_balance' => $index * 200,
                'is_active' => true,
            ],
        ))->values();

        $expenseCategories = collect([
            'Office Rent',
            'Internet Bill',
            'Electricity Bill',
            'Staff Lunch',
            'Transport',
            'Packaging',
            'Marketing',
            'Maintenance',
            'Bank Charge',
            'Miscellaneous',
        ])->map(fn (string $name): ExpenseCategory => ExpenseCategory::query()->updateOrCreate(
            ['slug' => Str::slug($name)],
            [
                'name' => $name,
                'description' => "Demo {$name} expense category.",
                'is_active' => true,
            ],
        ))->values();

        for ($index = 0; $index < 10; $index++) {
            $purchase = Purchase::query()->updateOrCreate(
                ['purchase_number' => 'PUR-DEMO-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT)],
                [
                    'supplier_id' => $suppliers[$index]->getKey(),
                    'purchase_date' => $today,
                    'discount' => $index * 100,
                    'vat' => 0,
                    'freight_to_ctg' => 1500 + ($index * 100),
                    'duty' => 900 + ($index * 75),
                    'c_and_f' => 500 + ($index * 50),
                    'truck' => 250 + ($index * 25),
                    'load_unload' => 150 + ($index * 15),
                    'custom_costs' => [
                        ['label' => 'Demo Handling', 'amount' => 100 + ($index * 10)],
                    ],
                    'paid_amount' => 5000 + ($index * 750),
                    'status' => 'received',
                    'update_cost_price' => false,
                    'note' => 'Today demo purchase with import costing fields.',
                ],
            );

            $this->purchaseItem($purchase, $products[$index], 15 + $index, (float) $products[$index]->cost_price);
            $this->purchaseItem($purchase, $products[($index + 1) % 10], 8 + $index, (float) $products[($index + 1) % 10]->cost_price);
        }

        for ($index = 0; $index < 10; $index++) {
            $customer = $customers[$index];
            $firstProduct = $products[$index];
            $secondProduct = $products[($index + 2) % 10];

            $order = Order::query()->updateOrCreate(
                ['order_number' => 'INV-DEMO-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT)],
                [
                    'customer_id' => $customer->getKey(),
                    'customer_name' => $customer->name,
                    'order_date' => $today,
                    'discount' => $index * 50,
                    'vat' => 0,
                    'paid_amount' => 1000 + ($index * 250),
                    'status' => $index % 3 === 0 ? 'confirmed' : 'completed',
                    'note' => 'Today demo invoice with partial payment.',
                ],
            );

            $this->orderItem($order, $firstProduct, 2 + ($index % 3), (float) ($firstProduct->sale_price ?? $firstProduct->price));
            $this->orderItem($order, $secondProduct, 1 + ($index % 2), (float) ($secondProduct->sale_price ?? $secondProduct->price));
        }

        $customers->each(function (Customer $customer, int $index) use ($accounts, $today): void {
            $customer->refresh();
            $amount = min(500 + ($index * 150), (float) $customer->current_balance);

            $this->customerPayment(
                $customer,
                $accounts[$index % $accounts->count()],
                $amount,
                $today,
                'CPAY-DEMO-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
            );
        });

        $suppliers->each(function (Supplier $supplier, int $index) use ($accounts, $today): void {
            $supplier->refresh();
            $amount = min(1000 + ($index * 200), (float) $supplier->current_balance);

            $this->supplierPayment(
                $supplier,
                $accounts[$index % $accounts->count()],
                $amount,
                $today,
                'SPAY-DEMO-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
            );
        });

        $expenseCategories->each(function (ExpenseCategory $category, int $index) use ($accounts, $today): void {
            Expense::query()->updateOrCreate(
                ['expense_number' => 'EXP-DEMO-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT)],
                [
                    'expense_category_id' => $category->getKey(),
                    'account_id' => $accounts[$index % $accounts->count()]->getKey(),
                    'amount' => 700 + ($index * 350),
                    'expense_date' => $today,
                    'reference' => 'DEMO-EXP-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                    'note' => 'Today demo expense.',
                ],
            );
        });
    }

    /**
     * Generates simple branded placeholder PNGs with GD so demo products
     * have a featured image plus a two-image gallery.
     */
    protected function attachProductImages(Product $product, int $index): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            return; // GD not installed — skip images silently.
        }

        $palette = ['#0F766E', '#B45309', '#1D4ED8', '#BE185D', '#4D7C0F', '#7C3AED', '#0E7490', '#B91C1C', '#A16207', '#334155'];
        $color = $palette[$index % count($palette)];

        $featured = $this->demoImage('products', $product->slug.'-main', $product->name, $color);
        $gallery = array_values(array_filter([
            $this->demoImage('products/gallery', $product->slug.'-alt-1', $product->name.' - view 2', $color),
            $this->demoImage('products/gallery', $product->slug.'-alt-2', $product->name.' - view 3', $this->shadeHex($color)),
        ]));

        $product->newQueryWithoutScopes()->whereKey($product->getKey())->update([
            'image' => $featured,
            'gallery_images' => json_encode($gallery),
        ]);
    }

    protected function demoImage(string $directory, string $name, string $label, string $hex): ?string
    {
        $path = $directory.'/'.$name.'.png';
        $disk = Storage::disk('public');

        if ($disk->exists($path)) {
            return $path;
        }

        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');

        $image = imagecreatetruecolor(800, 800);
        $background = imagecolorallocate($image, $r, $g, $b);
        $overlay = imagecolorallocatealpha($image, 255, 255, 255, 90);
        $textColor = imagecolorallocate($image, 255, 255, 255);

        imagefilledrectangle($image, 0, 0, 800, 800, $background);
        imagefilledellipse($image, 400, 330, 420, 420, $overlay);

        // Product initial big in the middle, name at the bottom.
        $initial = mb_strtoupper(mb_substr($label, 0, 1));
        imagestring($image, 5, 392, 315, $initial, $textColor);
        imagestring($image, 4, (int) max(20, 400 - (strlen($label) * 4)), 700, substr($label, 0, 90), $textColor);

        ob_start();
        imagepng($image);
        $contents = ob_get_clean();
        imagedestroy($image);

        $disk->put($path, $contents);

        return $path;
    }

    protected function shadeHex(string $hex): string
    {
        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');

        return sprintf('#%02x%02x%02x', (int) min(255, $r + 60), (int) min(255, $g + 40), (int) min(255, $b + 20));
    }

    protected function seedStorefrontPages(): void
    {
        $pages = [
            ['About Us', 'We are a demo company showcasing the ZamZam ERP storefront. This page is sample content.'],
            ['Contact', 'Reach the demo store via WhatsApp or phone. Sample contact information for testing.'],
            ['Return Policy', 'Products can be returned within 7 days of delivery in original condition. Demo policy text.'],
            ['Privacy Policy', 'We only use customer information to process orders. Demo privacy policy content.'],
            ['Terms and Conditions', 'By ordering from this demo storefront you agree to these sample terms.'],
            ['Warranty Policy', 'Applicable products carry manufacturer warranty. Demo warranty details.'],
            ['Shipping Information', 'Orders ship within 2-3 business days across the country. Demo shipping info.'],
            ['FAQ', 'Frequently asked demo questions about ordering, payment, and delivery.'],
            ['Wholesale Policy', 'Special pricing available for bulk orders. Contact us for demo wholesale terms.'],
            ['How to Order', 'Browse products, add to cart, confirm checkout, and track your order. Demo guide.'],
        ];

        foreach ($pages as $index => [$title, $content]) {
            StorefrontPage::query()->updateOrCreate(
                ['slug' => Str::slug($title)],
                [
                    'title' => $title,
                    'content' => $content,
                    'excerpt' => Str::limit($content, 120),
                    'is_published' => true,
                    'sort_order' => $index,
                ],
            );
        }
    }

    protected function seedCourierProviders(): void
    {
        CourierProvider::query()->updateOrCreate(
            ['slug' => 'demo-manual-courier'],
            [
                'name' => 'Demo Manual Courier',
                'driver' => 'manual',
                'is_active' => true,
                'settings' => [
                    'contact_person' => 'Demo Dispatcher',
                    'contact_phone' => '+8801700000010',
                    'delivery_fee_inside_dhaka' => 80,
                    'delivery_fee_outside_dhaka' => 130,
                ],
            ],
        );

        CourierProvider::query()->updateOrCreate(
            ['slug' => 'demo-steadfast'],
            [
                'name' => 'Demo Steadfast (sandbox)',
                'driver' => 'steadfast',
                'is_active' => false,
                'settings' => [
                    'contact_person' => 'Steadfast Demo',
                    'contact_phone' => '+8801700000011',
                ],
            ],
        );
    }

    /**
     * Turn two demo products into WooCommerce-style variable products so
     * variations can be tested in admin, storefront, cart, and checkout.
     */
    protected function seedVariableProducts($products): void
    {
        // Bluetooth Speaker — Color variations.
        $speaker = $products[1];
        $speaker->update([
            'has_variants' => true,
            'variant_attributes' => ['Color' => ['Black', 'Blue', 'Red']],
        ]);

        foreach ([
            ['Color' => 'Black', 'sku' => 'DEMO-SPEAKER-001-BLK', 'sale_price' => 1550, 'stock' => 12, 'hex' => '#1F2937'],
            ['Color' => 'Blue', 'sku' => 'DEMO-SPEAKER-001-BLU', 'sale_price' => 1550, 'stock' => 8, 'hex' => '#1D4ED8'],
            ['Color' => 'Red', 'sku' => 'DEMO-SPEAKER-001-RED', 'sale_price' => 1650, 'stock' => 5, 'hex' => '#B91C1C'],
        ] as $index => $row) {
            $variantImages = function_exists('imagecreatetruecolor')
                ? array_values(array_filter([
                    $this->demoImage('products/variants', $speaker->slug.'-'.Str::slug($row['Color']), $speaker->name.' - '.$row['Color'], $row['hex']),
                ]))
                : null;

            ProductVariant::query()->updateOrCreate(
                ['product_id' => $speaker->getKey(), 'sku' => $row['sku']],
                [
                    'company_id' => $speaker->company_id,
                    'options' => ['Color' => $row['Color']],
                    'sale_price' => $row['sale_price'],
                    'cost_price' => 900,
                    'stock' => $row['stock'],
                    'images' => $variantImages,
                    'is_active' => true,
                    'sort_order' => $index,
                ],
            );
        }

        // Keyboard Mouse Combo — Layout + Color variations.
        $combo = $products[5];
        $combo->update([
            'has_variants' => true,
            'variant_attributes' => ['Layout' => ['English', 'Bangla'], 'Color' => ['Black', 'White']],
        ]);

        foreach ([
            ['options' => ['Layout' => 'English', 'Color' => 'Black'], 'sku' => 'DEMO-KB-MOUSE-001-EN-BLK', 'stock' => 10],
            ['options' => ['Layout' => 'English', 'Color' => 'White'], 'sku' => 'DEMO-KB-MOUSE-001-EN-WHT', 'stock' => 6],
            ['options' => ['Layout' => 'Bangla', 'Color' => 'Black'], 'sku' => 'DEMO-KB-MOUSE-001-BN-BLK', 'stock' => 4],
            ['options' => ['Layout' => 'Bangla', 'Color' => 'White'], 'sku' => 'DEMO-KB-MOUSE-001-BN-WHT', 'stock' => 0],
        ] as $index => $row) {
            ProductVariant::query()->updateOrCreate(
                ['product_id' => $combo->getKey(), 'sku' => $row['sku']],
                [
                    'company_id' => $combo->company_id,
                    'options' => $row['options'],
                    'sale_price' => 1650 + ($index * 50),
                    'cost_price' => 950,
                    'stock' => $row['stock'],
                    'is_active' => true,
                    'sort_order' => $index,
                ],
            );
        }
    }

    protected function seedCarousels($products): void
    {
        $carousel = ProductCarousel::query()->updateOrCreate(
            ['company_id' => $products[0]->company_id, 'title' => 'Best Sellers'],
            [
                'subtitle' => 'Most popular demo picks',
                'is_active' => true,
                'sort_order' => 0,
            ],
        );

        $carousel->products()->syncWithoutDetaching([
            $products[0]->getKey() => ['sort_order' => 0],
            $products[1]->getKey() => ['sort_order' => 1],
            $products[4]->getKey() => ['sort_order' => 2],
            $products[7]->getKey() => ['sort_order' => 3],
        ]);

        $newArrivals = ProductCarousel::query()->updateOrCreate(
            ['company_id' => $products[0]->company_id, 'title' => 'New Arrivals'],
            [
                'subtitle' => 'Fresh in the demo catalog',
                'is_active' => true,
                'sort_order' => 1,
            ],
        );

        $newArrivals->products()->syncWithoutDetaching([
            $products[5]->getKey() => ['sort_order' => 0],
            $products[8]->getKey() => ['sort_order' => 1],
            $products[9]->getKey() => ['sort_order' => 2],
        ]);
    }

    protected function category(string $name, string $description): Category
    {
        return Category::query()->updateOrCreate(
            ['slug' => Str::slug($name)],
            [
                'name' => $name,
                'description' => $description,
                'is_active' => true,
            ],
        );
    }

    protected function product(Category $category, array $data): Product
    {
        return Product::query()->updateOrCreate(
            ['sku' => $data['sku']],
            [
                'name' => $data['name'],
                'description' => 'Demo product for dashboard presentation.',
                'barcode' => $data['barcode'],
                'unit' => 'pcs',
                'brand' => $data['brand'],
                'cost_price' => $data['cost_price'],
                'sale_price' => $data['sale_price'],
                'price' => $data['price'],
                'reorder_level' => $data['reorder_level'],
                'vat_rate' => 0,
                'is_active' => true,
                'status' => Product::STATUS_AVAILABLE,
                'category_id' => $category->getKey(),
            ],
        );
    }

    protected function openingStock(Product $product, int $quantity): void
    {
        StockMovement::query()->updateOrCreate(
            [
                'product_id' => $product->getKey(),
                'type' => 'opening',
                'reference_type' => Product::class,
                'reference_id' => $product->getKey(),
            ],
            [
                'quantity' => $quantity,
                'note' => 'Demo opening stock',
            ],
        );
    }

    protected function purchaseItem(Purchase $purchase, Product $product, int $quantity, float $unitCost): void
    {
        PurchaseItem::query()->updateOrCreate(
            [
                'purchase_id' => $purchase->getKey(),
                'product_id' => $product->getKey(),
            ],
            [
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
            ],
        );
    }

    protected function orderItem(Order $order, Product $product, int $quantity, float $unitPrice): void
    {
        OrderItem::query()->updateOrCreate(
            [
                'order_id' => $order->getKey(),
                'product_id' => $product->getKey(),
            ],
            [
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
            ],
        );
    }

    protected function customerPayment(
        Customer $customer,
        Account $account,
        float $amount,
        string $date,
        string $number = 'CPAY-DEMO-0001',
    ): void {
        if ($amount <= 0) {
            return;
        }

        CustomerPayment::query()->updateOrCreate(
            ['payment_number' => $number],
            [
                'customer_id' => $customer->getKey(),
                'account_id' => $account->getKey(),
                'amount' => $amount,
                'payment_date' => $date,
                'method' => $account->type === 'bank' ? 'bank' : 'cash',
                'reference' => $number,
                'note' => 'Demo customer payment.',
            ],
        );
    }

    protected function supplierPayment(
        Supplier $supplier,
        Account $account,
        float $amount,
        string $date,
        string $number = 'SPAY-DEMO-0001',
    ): void {
        if ($amount <= 0) {
            return;
        }

        SupplierPayment::query()->updateOrCreate(
            ['payment_number' => $number],
            [
                'supplier_id' => $supplier->getKey(),
                'account_id' => $account->getKey(),
                'amount' => $amount,
                'payment_date' => $date,
                'method' => 'cash',
                'reference' => $number,
                'note' => 'Demo supplier payment.',
            ],
        );
    }
}
