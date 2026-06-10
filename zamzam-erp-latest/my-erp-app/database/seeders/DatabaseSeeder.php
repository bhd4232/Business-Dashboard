<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\SiteBanner;
use App\Models\SiteMenu;
use App\Models\SitePage;
use App\Models\SiteSection;
use App\Models\SiteSetting;
use HasinHayder\Tyro\Models\Privilege;
use HasinHayder\Tyro\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $roles = [
            'super_admin' => 'Super Admin',
            'manager' => 'Manager',
            'sales_staff' => 'Sales Staff',
            'inventory_staff' => 'Inventory Staff',
            'accountant' => 'Accountant',
            'user' => 'User',
        ];

        $roleModels = collect($roles)->mapWithKeys(function (string $name, string $slug) {
            return [
                $slug => Role::query()->updateOrCreate(
                    ['slug' => $slug],
                    ['name' => $name],
                ),
            ];
        });

        $privileges = [
            '*' => 'All privileges',
            'dashboard.view' => 'View dashboard',
            'inventory.view' => 'View inventory',
            'inventory.manage' => 'Manage inventory',
            'purchasing.view' => 'View purchasing',
            'purchasing.manage' => 'Manage purchasing',
            'sales.view' => 'View sales',
            'sales.manage' => 'Manage sales',
            'accounts.view' => 'View accounts',
            'accounts.manage' => 'Manage accounts',
            'reports.view' => 'View reports',
            'reports.export' => 'Export reports',
            'website.view' => 'View website management',
            'website.manage' => 'Manage website content',
            'users.manage' => 'Manage users and roles',
            'audit.view' => 'View audit logs',
        ];

        $privilegeModels = collect($privileges)->mapWithKeys(function (string $name, string $slug) {
            return [
                $slug => Privilege::query()->updateOrCreate(
                    ['slug' => $slug],
                    [
                        'name' => $name,
                        'description' => $name,
                    ],
                ),
            ];
        });

        $rolePrivilegeMap = [
            'super_admin' => ['*'],
            'manager' => [
                'dashboard.view',
                'inventory.view',
                'inventory.manage',
                'purchasing.view',
                'purchasing.manage',
                'sales.view',
                'sales.manage',
                'accounts.view',
                'accounts.manage',
                'reports.view',
                'reports.export',
                'website.view',
                'website.manage',
            ],
            'sales_staff' => [
                'dashboard.view',
                'inventory.view',
                'sales.view',
                'sales.manage',
                'reports.view',
                'website.view',
            ],
            'inventory_staff' => [
                'dashboard.view',
                'inventory.view',
                'inventory.manage',
                'purchasing.view',
                'reports.view',
            ],
            'accountant' => [
                'dashboard.view',
                'purchasing.view',
                'sales.view',
                'accounts.view',
                'accounts.manage',
                'reports.view',
                'reports.export',
            ],
            'user' => ['dashboard.view'],
        ];

        foreach ($rolePrivilegeMap as $roleSlug => $privilegeSlugs) {
            $roleModels[$roleSlug]->privileges()->sync(
                $privilegeModels->only($privilegeSlugs)->pluck('id')->all(),
            );
        }

        $admin = User::query()->updateOrCreate([
            'email' => 'admin@zamzamint.com',
        ], [
            'name' => 'ZamZam Admin',
            'password' => Hash::make('password'),
        ]);

        $admin->assignRole($roleModels['super_admin']);

        SiteSetting::query()->updateOrCreate([
            'id' => 1,
        ], [
            'site_name' => 'ZamZam International',
            'tagline' => 'China to Bangladesh wholesale ERP operations',
            'header_show_site_name' => false,
            'header_show_tagline' => false,
            'header_logo_width' => 190,
            'header_logo_height' => 64,
            'phone' => '+880 1XXX-XXXXXX',
            'email' => 'admin@zamzamint.com',
            'address' => 'Dhaka, Bangladesh',
            'footer_text' => 'ZamZam International. China to Bangladesh wholesale ERP and trading operations.',
            'seo_title' => 'ZamZam International - Wholesale ERP',
            'seo_description' => 'China to Bangladesh wholesale purchase, inventory, sales, accounts, and website management from one ERP dashboard.',
            'is_active' => true,
        ]);

        SiteBanner::query()->updateOrCreate([
            'id' => 1,
        ], [
            'title' => 'China to Bangladesh Wholesale Management',
            'subtitle' => 'ZamZam International',
            'description' => 'Manage supplier purchase, import costing, warehouse stock, wholesale sales, dues, accounts, reports, and website content from one Tyro-powered Laravel dashboard.',
            'primary_button_label' => 'Open Dashboard',
            'primary_button_url' => '/admin',
            'secondary_button_label' => 'View Website Content',
            'secondary_button_url' => '#about',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        SitePage::query()->updateOrCreate([
            'slug' => 'about',
        ], [
            'title' => 'About ZamZam International',
            'excerpt' => 'A wholesale trading operation focused on China-to-Bangladesh sourcing, costing, inventory, and distribution.',
            'content' => '<p>ZamZam International manages purchase, shipment costing, stock, sales invoice, customer due, supplier payable, accounts, and reports through a connected ERP workflow.</p>',
            'seo_title' => 'About ZamZam International',
            'seo_description' => 'Learn about ZamZam International wholesale trading and ERP operations.',
            'is_published' => true,
            'sort_order' => 1,
        ]);

        SitePage::query()->updateOrCreate([
            'slug' => 'services',
        ], [
            'title' => 'Services',
            'excerpt' => 'Wholesale sourcing, import costing, inventory, sales, and business reporting support.',
            'content' => '<p>The ERP supports product catalog, supplier purchase, China-to-BD costing, stock movement, invoice, due management, payments, expenses, ledger, and reports.</p>',
            'seo_title' => 'ZamZam Wholesale Services',
            'seo_description' => 'Wholesale sourcing, stock, sales, payment, and reporting services.',
            'is_published' => true,
            'sort_order' => 2,
        ]);

        $sections = [
            [
                'key' => 'purchase-costing',
                'section_type' => 'service_block',
                'title' => 'Purchase Costing',
                'subtitle' => 'Import operations',
                'body' => '<p>Track purchase item cost, China-to-Bangladesh expenses, supplier due, and landed cost planning.</p>',
                'placement' => 'home',
                'layout' => 'card',
                'sort_order' => 1,
            ],
            [
                'key' => 'inventory-control',
                'section_type' => 'service_block',
                'title' => 'Inventory Control',
                'subtitle' => 'Stock visibility',
                'body' => '<p>Keep stock traceable through opening, purchase, sale, return, and adjustment movements.</p>',
                'placement' => 'home',
                'layout' => 'card',
                'sort_order' => 2,
            ],
            [
                'key' => 'sales-accounts',
                'section_type' => 'service_block',
                'title' => 'Sales and Accounts',
                'subtitle' => 'Connected finance',
                'body' => '<p>Connect invoices, customer payments, supplier payments, expenses, ledgers, and reports.</p>',
                'placement' => 'home',
                'layout' => 'card',
                'sort_order' => 3,
            ],
            [
                'key' => 'featured-categories',
                'section_type' => 'featured_categories',
                'title' => 'Featured Categories',
                'subtitle' => 'Product groups',
                'body' => '<p>Show key product categories here now. In Phase 3 this can be connected to live ERP categories.</p>',
                'button_label' => 'View Products',
                'button_url' => '/products',
                'placement' => 'home',
                'layout' => 'wide',
                'sort_order' => 4,
            ],
            [
                'key' => 'featured-products-placeholder',
                'section_type' => 'featured_products_placeholder',
                'title' => 'Featured Products',
                'subtitle' => 'Catalog preview',
                'body' => '<p>This placeholder keeps the public website ready for featured products before the Product and Inventory module is connected.</p>',
                'button_label' => 'Open Product Page',
                'button_url' => '/products',
                'placement' => 'home',
                'layout' => 'card',
                'sort_order' => 5,
            ],
            [
                'key' => 'cta-contact',
                'section_type' => 'cta_contact',
                'title' => 'Need wholesale support?',
                'subtitle' => 'Contact block',
                'body' => '<p>Send a message from the website and manage every inquiry from Contact Messages in the dashboard.</p>',
                'button_label' => 'Contact Us',
                'button_url' => '/contact',
                'placement' => 'home',
                'layout' => 'wide',
                'sort_order' => 6,
            ],
        ];

        foreach ($sections as $section) {
            SiteSection::query()->updateOrCreate([
                'key' => $section['key'],
            ], $section + ['is_active' => true]);
        }

        $menus = [
            ['label' => 'Home', 'url' => '/', 'location' => 'header', 'sort_order' => 1],
            ['label' => 'About', 'url' => '/about', 'location' => 'header', 'sort_order' => 2],
            ['label' => 'Services', 'url' => '/pages/services', 'location' => 'header', 'sort_order' => 3],
            ['label' => 'Products', 'url' => '/products', 'location' => 'header', 'sort_order' => 4],
            ['label' => 'Contact', 'url' => '/contact', 'location' => 'header', 'sort_order' => 5],
            ['label' => 'About', 'url' => '/about', 'location' => 'footer', 'sort_order' => 1],
            ['label' => 'Services', 'url' => '/pages/services', 'location' => 'footer', 'sort_order' => 2],
            ['label' => 'Products', 'url' => '/products', 'location' => 'footer', 'sort_order' => 3],
        ];

        foreach ($menus as $menu) {
            SiteMenu::query()->updateOrCreate([
                'label' => $menu['label'],
                'location' => $menu['location'],
            ], $menu + ['is_active' => true]);
        }
    }
}
