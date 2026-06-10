<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // ─── Permissions grouped by module ────────────────
        $permissions = [
            // Auth & Users
            'auth' => [
                'users.view', 'users.create', 'users.edit', 'users.delete',
                'roles.view', 'roles.manage',
            ],
            // Procurement
            'procurement' => [
                'suppliers.view', 'suppliers.create', 'suppliers.edit', 'suppliers.delete',
                'products.view', 'products.create', 'products.edit', 'products.delete',
                'categories.view', 'categories.create', 'categories.edit',
                'purchase_orders.view', 'purchase_orders.create', 'purchase_orders.edit',
                'purchase_orders.confirm', 'purchase_orders.delete',
            ],
            // Shipping
            'shipping' => [
                'shipments.view', 'shipments.create', 'shipments.edit',
                'couriers.view', 'couriers.create', 'couriers.edit', 'couriers.delete',
                'parcels.view', 'parcels.create', 'parcels.edit',
            ],
            // Inventory
            'inventory' => [
                'inventory.view', 'inventory.adjust',
                'warehouses.view', 'warehouses.manage',
                'stock_transfers.view', 'stock_transfers.create',
            ],
            // Sales
            'sales' => [
                'customers.view', 'customers.create', 'customers.edit', 'customers.delete',
                'sales_orders.view', 'sales_orders.create', 'sales_orders.edit',
                'sales_orders.confirm', 'sales_orders.cancel', 'sales_orders.delete',
                'invoices.view', 'invoices.create', 'invoices.edit',
                'returns.view', 'returns.create', 'returns.approve',
            ],
            // Finance
            'finance' => [
                'payments.view', 'payments.create', 'payments.edit', 'payments.delete',
                'accounts.view', 'accounts.manage',
                'expenses.view', 'expenses.create', 'expenses.approve',
                'reports.view', 'reports.export',
            ],
            // Chat
            'chat' => [
                'conversations.view', 'conversations.reply',
                'workflows.view', 'workflows.manage',
                'whatsapp.manage',
            ],
            // Settings
            'settings' => [
                'settings.view', 'settings.manage',
                'modules.manage',
                'admin.trash.purge',
            ],
        ];

        // Create all permissions
        foreach ($permissions as $module => $perms) {
            foreach ($perms as $perm) {
                Permission::firstOrCreate(
                    ['name' => $perm, 'guard_name' => 'web'],
                    ['module' => $module]
                );
            }
        }

        // ─── Roles ────────────────────────────────────────
        $roles = [
            'admin' => array_merge(...array_values($permissions)), // all permissions
            'manager' => [
                // All except delete and settings
                'suppliers.view', 'suppliers.create', 'suppliers.edit',
                'products.view', 'products.create', 'products.edit',
                'categories.view', 'categories.create', 'categories.edit',
                'purchase_orders.view', 'purchase_orders.create', 'purchase_orders.edit', 'purchase_orders.confirm', 'purchase_orders.delete',
                'shipments.view', 'shipments.create', 'shipments.edit',
                'couriers.view', 'couriers.create', 'couriers.edit',
                'parcels.view', 'parcels.create', 'parcels.edit',
                'inventory.view', 'inventory.adjust',
                'warehouses.view',
                'stock_transfers.view', 'stock_transfers.create',
                'customers.view', 'customers.create', 'customers.edit',
                'sales_orders.view', 'sales_orders.create', 'sales_orders.edit', 'sales_orders.confirm', 'sales_orders.cancel',
                'invoices.view', 'invoices.create', 'invoices.edit',
                'returns.view', 'returns.create', 'returns.approve',
                'payments.view', 'payments.create', 'payments.edit',
                'accounts.view',
                'expenses.view', 'expenses.create', 'expenses.approve',
                'reports.view', 'reports.export',
                'conversations.view', 'conversations.reply',
                'workflows.view',
            ],
            'accountant' => [
                'payments.view', 'payments.create', 'payments.edit',
                'accounts.view', 'accounts.manage',
                'expenses.view', 'expenses.create', 'expenses.approve',
                'reports.view', 'reports.export',
                'invoices.view',
                'customers.view',
            ],
            'salesman' => [
                'customers.view', 'customers.create', 'customers.edit',
                'sales_orders.view', 'sales_orders.create', 'sales_orders.edit', 'sales_orders.confirm',
                'invoices.view', 'invoices.create',
                'returns.view', 'returns.create',
                'parcels.view', 'parcels.create', 'parcels.edit',
                'payments.view', 'payments.create',
                'inventory.view',
                'conversations.view', 'conversations.reply',
            ],
            'storekeeper' => [
                'inventory.view', 'inventory.adjust',
                'warehouses.view',
                'stock_transfers.view', 'stock_transfers.create',
                'purchase_orders.view',
                'shipments.view',
            ],
            'procurement' => [
                'suppliers.view', 'suppliers.create', 'suppliers.edit',
                'products.view', 'products.create', 'products.edit',
                'categories.view', 'categories.create',
                'purchase_orders.view', 'purchase_orders.create', 'purchase_orders.edit', 'purchase_orders.confirm', 'purchase_orders.delete',
                'shipments.view', 'shipments.create', 'shipments.edit',
                'inventory.view',
            ],
            'reseller' => [
                // Reseller panel only - handled separately
                'sales_orders.view', 'invoices.view',
            ],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($rolePermissions);
        }
    }
}
