# Module 1: Auth & User Management

## Overview

Authentication, authorization, and user management system with role-based access control (RBAC). Includes the Reseller role with a separate self-service panel.

## Database Tables

### users
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | Auto increment |
| name | varchar(255) | Full name |
| email | varchar(255) | Unique, login credential |
| phone | varchar(20) | Unique, for BD users |
| password | varchar(255) | Bcrypt hash |
| email_verified_at | timestamp nullable | |
| phone_verified_at | timestamp nullable | |
| is_active | boolean default true | |
| last_login_at | timestamp nullable | |
| profile_photo_path | varchar(2048) nullable | |
| remember_token | varchar(100) nullable | |
| created_at | timestamp | |
| updated_at | timestamp | |

### roles
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| name | varchar(255) | admin, manager, accountant, salesman, storekeeper, procurement, reseller |
| guard_name | varchar(255) | web / api |
| created_at | timestamp | |
| updated_at | timestamp | |

### permissions
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| name | varchar(255) | e.g. `product.create`, `sales.order.view` |
| guard_name | varchar(255) | web / api |
| module | varchar(100) | for grouping: procurement, sales, inventory, etc. |
| created_at | timestamp | |
| updated_at | timestamp | |

### model_has_roles (Spatie package)
Standard Spatie Laravel Permission pivot table.

### role_has_permissions (Spatie package)
Standard Spatie Laravel Permission pivot table.

### model_has_permissions (Spatie package)
Standard Spatie Laravel Permission pivot table.

### activity_log
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| user_id | bigint FK nullable | Who performed the action |
| subject_type | varchar(255) nullable | Model class |
| subject_id | bigint nullable | Model ID |
| event | varchar(255) | created, updated, deleted |
| properties | json nullable | Old/new values |
| ip_address | varchar(45) nullable | |
| user_agent | varchar(500) nullable | |
| created_at | timestamp | |

## Permission Structure

### Module-based Permissions Pattern
Each module follows this pattern:
```
{module}.view       - Can view list
{module}.create     - Can create new
{module}.update     - Can edit existing
{module}.delete     - Can delete
{module}.export     - Can export data
{module}.approve    - Can approve/reject (where applicable)
```

### Complete Permission List

| Module | Permissions |
|--------|------------|
| auth | auth.user.view, auth.user.create, auth.user.update, auth.user.delete |
| role | auth.role.view, auth.role.create, auth.role.update, auth.role.delete |
| supplier | supplier.view, supplier.create, supplier.update, supplier.delete, supplier.export |
| purchase | purchase.view, purchase.create, purchase.update, purchase.delete, purchase.approve, purchase.export |
| shipping | shipping.view, shipping.create, shipping.update, shipping.delete, shipping.export |
| inventory | inventory.view, inventory.create, inventory.update, inventory.adjust, inventory.transfer, inventory.export |
| product | product.view, product.create, product.update, product.delete, product.export |
| wholesale | wholesale.view, wholesale.create, wholesale.update, wholesale.delete, wholesale.approve, wholesale.export |
| retail | retail.view, retail.create, retail.update, retail.delete, retail.export |
| credit | credit.view, credit.create, credit.update, credit.approve, credit.export |
| payment | payment.view, payment.create, payment.update, payment.export |
| accounts | accounts.view, accounts.create, accounts.update, accounts.export |
| report | report.view, report.sales, report.inventory, report.profit, report.credit, report.shipping |
| woocommerce | woocommerce.view, woocommerce.configure, woocommerce.sync |
| reseller-panel | reseller-panel.view, reseller-panel.order, reseller-panel.payment |
| import | import.customers, import.products, import.suppliers, import.delete |
| settings | settings.view, settings.manage |

## Role-Permission Matrix

| Permission | Admin | Manager | Accountant | Salesman | Storekeeper | Procurement | Reseller |
|-----------|-------|---------|------------|----------|-------------|-------------|----------|
| auth.* | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| supplier.* | ✅ | ✅ (no delete) | ❌ | ❌ | ❌ | ✅ | ❌ |
| purchase.* | ✅ | ✅ (no delete) | view | ❌ | ❌ | ✅ | ❌ |
| shipping.* | ✅ | ✅ (no delete) | ❌ | ❌ | ❌ | ✅ | ❌ |
| inventory.* | ✅ | ✅ (no delete) | view | ❌ | ✅ | ❌ | ❌ |
| product.* | ✅ | ✅ (no delete) | view | view | view | view | ❌ |
| wholesale.* | ✅ | ✅ (no delete/approve) | view | ✅ | ❌ | ❌ | ❌ |
| retail.* | ✅ | ✅ (no delete) | ❌ | ✅ | ❌ | ❌ | ❌ |
| credit.* | ✅ | ✅ | ✅ | view | ❌ | ❌ | ❌ |
| payment.* | ✅ | ✅ | ✅ | create,view | ❌ | ❌ | ❌ |
| accounts.* | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| report.* | ✅ | ✅ | sales,credit,profit | sales | inventory | shipping | ❌ |
| woocommerce.* | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| reseller-panel.* | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ (own data only) |

## API Routes

### Auth
| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| POST | /api/auth/login | Login (email + password) | No |
| POST | /api/auth/login/phone | Login (phone + OTP) | No |
| POST | /api/auth/register | Reseller self-registration | No |
| POST | /api/auth/logout | Logout | Yes |
| POST | /api/auth/refresh | Refresh JWT token | Yes |
| GET | /api/auth/me | Get current user + role | Yes |
| PUT | /api/auth/me | Update profile | Yes |
| PUT | /api/auth/password | Change password | Yes |

### User Management (Admin/Manager only)
| Method | Endpoint | Description | Permission |
|--------|----------|-------------|-----------|
| GET | /api/users | List users (paginated, filterable) | auth.user.view |
| POST | /api/users | Create user | auth.user.create |
| GET | /api/users/{id} | Get user detail | auth.user.view |
| PUT | /api/users/{id} | Update user | auth.user.update |
| DELETE | /api/users/{id} | Deactivate user | auth.user.delete |
| PUT | /api/users/{id}/roles | Assign roles | auth.role.update |

### Role Management (Admin only)
| Method | Endpoint | Description | Permission |
|--------|----------|-------------|-----------|
| GET | /api/roles | List roles | auth.role.view |
| POST | /api/roles | Create role | auth.role.create |
| PUT | /api/roles/{id} | Update role + permissions | auth.role.update |
| DELETE | /api/roles/{id} | Delete role | auth.role.delete |

## Frontend Pages (Inertia.js + Vue)

### Admin Pages
| Page | Route | Component |
|------|-------|-----------|
| Login | /login | Auth/Login.vue |
| Dashboard | / | Dashboard/Index.vue |
| Users List | /users | Users/Index.vue |
| User Create | /users/create | Users/Create.vue |
| User Edit | /users/{id}/edit | Users/Edit.vue |
| Roles List | /roles | Roles/Index.vue |
| Role Edit | /roles/{id}/edit | Roles/Edit.vue |
| Activity Log | /activity-log | ActivityLog/Index.vue |
| My Profile | /profile | Profile/Index.vue |

## Business Logic

### Login Flow
1. User submits email + password (or phone + OTP)
2. System validates credentials
3. Check `is_active` flag
4. Generate JWT token (for API) or session (for web)
5. Load user's roles and permissions
6. Log activity (login event)
7. Update `last_login_at`

### Reseller Registration Flow
1. Reseller fills registration form on **native Wholesale Storefront** (`/wholesale/register`) or ERP registration page
2. ERP creates user with `reseller` role (status: pending) + `reseller_profiles` record
3. Admin/Manager reviews in ERP (verify trade license, NID, business details)
4. On approval:
   - `approval_status` → approved
   - Price tier & credit limit assigned
   - User activated (`is_active = true`)
   - Welcome email sent with ERP panel login link
5. Reseller can now:
   - Login to ERP Reseller Panel (`/reseller`)
   - Place orders on native Wholesale Storefront (`/wholesale`)
6. Credit limit assigned by Admin

> **Note:** WooCommerce integration is a **one-time data importer only** — not an ongoing sync. New reseller registrations go through the native storefront, not WooCommerce.

### Password Policy
- Minimum 8 characters
- Must contain: 1 uppercase, 1 lowercase, 1 number
- Password reset via email link
- Phone OTP reset (optional)

### Session/Token Policy
- Sanctum API token: no expiry by default (revocable on logout)
- Web session: 2 hours idle timeout
- Refresh token: 7 days (if token rotation enabled)
- Reseller panel: same as web session

> **Note:** ZamZam ERP uses `laravel/sanctum` for API authentication — not JWT. Sanctum issues opaque API tokens stored in the `personal_access_tokens` table, not JWT tokens.

## Events & Listeners

| Event | Listener | Description |
|-------|----------|-------------|
| UserLoggedIn | LogLoginActivity | Record login in activity_log |
| UserCreated | SendWelcomeEmail | Send welcome email with setup link |
| ResellerRegistered | NotifyAdmins | Notify admin/managers of new reseller |
| ResellerApproved | ActivateResellerAccount | Enable reseller panel + WooCommerce access |
| RoleChanged | ClearUserCache | Clear permission cache for affected users |

## Validation Rules

### User Create
```php
'name'            => 'required|string|max:255',
'email'           => 'required|email|unique:users,email',
'phone'           => 'required|string|max:20|unique:users,phone',
'password'        => 'required|string|min:8|confirmed',
'roles'           => 'required|array',
'roles.*'         => 'exists:roles,id',
'is_active'       => 'boolean',
```

### User Update
```php
'name'            => 'required|string|max:255',
'email'           => 'required|email|unique:users,email,{id}',
'phone'           => 'nullable|string|max:20|unique:users,phone,{id}',
'password'        => 'nullable|string|min:8|confirmed',
'roles'           => 'sometimes|array',
'roles.*'         => 'exists:roles,id',
'is_active'       => 'boolean',
```

### Login
```php
'email'    => 'required|email',
'password' => 'required|string',
```

## Packages

- **spatie/laravel-permission**: ^6.0 (Role & Permission management)
- **spatie/laravel-activitylog**: ^4.0 (Activity logging)
- **laravel/sanctum**: ^4.0 (API token authentication)
- **laravel/fortify**: ^1.0 (Authentication scaffolding)

## Developer Notes

1. Use Laravel Fortify for authentication scaffolding (login, register, password reset)
2. Use Spatie Permission for RBAC - add `module` column to permissions table for grouping
3. All API routes must have `auth:sanctum` middleware
4. Reseller API routes must have `role:reseller` middleware
5. Activity log must capture: who, what, when, old values, new values
6. Use Laravel Policies for fine-grained authorization checks
7. Cache user permissions in Redis for performance
8. Password must never be logged or exposed in API responses
