# Roles and Permissions

Business Dashboard uses role-based permissions for Filament admin access and custom business actions.

## Built-In Roles

| Role | Intended Use |
|---|---|
| Super Admin | Full system owner with all permissions |
| Manager | Business manager with sales, purchase, inventory, accounts, and report access |
| Sales Staff | Sales user with customer/order/report view access |
| Inventory Staff | Inventory and purchase user |
| Accountant | Accounts, payments, expenses, and report export user |

## Permission Matrix

| Module | Super Admin | Manager | Sales Staff | Inventory Staff | Accountant |
|---|---:|---:|---:|---:|---:|
| Dashboard | Full | View | View | View | View |
| Sales | Full | Create/Edit | Create/Edit | View | View |
| Purchasing | Full | Create/Edit | No | View/Create/Edit | View |
| Inventory | Full | Create/Edit | View | Create/Edit | No |
| Accounts | Full | Create/Edit | No | No | Create/Edit |
| Reports | Full | View/Export | View | View | View/Export |
| Settings | Full | No | No | No | No |
| Backups | Full | No | No | No | No |
| Users/Roles | Full | No | No | No | No |

## Permission Keys

Available permission keys:

```txt
dashboard.view
sales.view
sales.create
sales.update
sales.delete
purchasing.view
purchasing.create
purchasing.update
purchasing.delete
inventory.view
inventory.create
inventory.update
inventory.delete
accounts.view
accounts.create
accounts.update
accounts.delete
reports.view
reports.export
backups.manage
settings.manage
users.manage
```

## Custom Roles

Custom roles can be created from the admin panel. A custom role stores:

- Name
- Slug
- Permission list
- Active/inactive state

Users assigned to inactive custom roles receive no permissions from that role.

## Security Rules

- A user with no role falls back to `sales_staff`, not `super_admin`.
- Only Super Admin can delete sensitive financial records.
- A user cannot deactivate their own account.
- The last active Super Admin cannot be downgraded or deleted.
- Report export requires `reports.export`.
- Backup access requires `backups.manage` or Super Admin.
- Company settings access requires `settings.manage` or Super Admin.

## Recommended Production Practice

- Keep at least two active Super Admin users.
- Use custom roles for narrow client-specific jobs.
- Review inactive users monthly.
- Review audit logs after role or payment changes.
- Avoid sharing admin accounts between staff.
