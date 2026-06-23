<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Concerns\ValidatesEmailAddress;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, ValidatesEmailAddress;

    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            static::validateEmailAttribute($user, required: true);

            if (! $user->exists) {
                return;
            }

            if (Auth::id() === $user->getKey() && $user->isDirty('is_active') && $user->is_active === false) {
                throw ValidationException::withMessages([
                    'is_active' => 'You cannot deactivate your own user account.',
                ]);
            }

            $wasSuperAdmin = $user->getOriginal('role') === 'super_admin';
            $willRemainActiveSuperAdmin = $user->effectiveRole() === 'super_admin' && $user->is_active !== false;

            if ($wasSuperAdmin && ! $willRemainActiveSuperAdmin && static::activeSuperAdminCount($user->getKey()) === 0) {
                throw ValidationException::withMessages([
                    'role' => 'At least one active Super Admin must remain.',
                ]);
            }
        });

        static::deleting(function (User $user): void {
            if (Auth::id() === $user->getKey()) {
                throw ValidationException::withMessages([
                    'user' => 'You cannot delete your own user account.',
                ]);
            }

            if ($user->isSuperAdmin() && $user->is_active !== false && static::activeSuperAdminCount($user->getKey()) === 0) {
                throw ValidationException::withMessages([
                    'user' => 'At least one active Super Admin must remain.',
                ]);
            }
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active !== false;
    }

    public const ROLES = [
        'super_admin' => 'Super Admin',
        'manager' => 'Manager',
        'sales_staff' => 'Sales Staff',
        'inventory_staff' => 'Inventory Staff',
        'accountant' => 'Accountant',
    ];

    public const ROLE_PERMISSIONS = [
        'super_admin' => ['*'],
        'manager' => [
            'dashboard.view',
            'sales.view',
            'sales.create',
            'sales.update',
            'purchasing.view',
            'purchasing.create',
            'purchasing.update',
            'inventory.view',
            'inventory.create',
            'inventory.update',
            'accounts.view',
            'accounts.create',
            'accounts.update',
            'reports.view',
            'reports.export',
        ],
        'sales_staff' => [
            'dashboard.view',
            'sales.view',
            'sales.create',
            'sales.update',
            'inventory.view',
            'reports.view',
        ],
        'inventory_staff' => [
            'dashboard.view',
            'inventory.view',
            'inventory.create',
            'inventory.update',
            'purchasing.view',
            'reports.view',
        ],
        'accountant' => [
            'dashboard.view',
            'sales.view',
            'purchasing.view',
            'accounts.view',
            'accounts.create',
            'accounts.update',
            'reports.view',
            'reports.export',
        ],
    ];

    public const CUSTOM_PERMISSION_OPTIONS = [
        'dashboard.view' => 'Dashboard: View',
        'sales.view' => 'Sales: View',
        'sales.create' => 'Sales: Create',
        'sales.update' => 'Sales: Update',
        'sales.delete' => 'Sales: Delete',
        'purchasing.view' => 'Purchasing: View',
        'purchasing.create' => 'Purchasing: Create',
        'purchasing.update' => 'Purchasing: Update',
        'purchasing.delete' => 'Purchasing: Delete',
        'inventory.view' => 'Inventory: View',
        'inventory.create' => 'Inventory: Create',
        'inventory.update' => 'Inventory: Update',
        'inventory.delete' => 'Inventory: Delete',
        'accounts.view' => 'Accounts: View',
        'accounts.create' => 'Accounts: Create',
        'accounts.update' => 'Accounts: Update',
        'accounts.delete' => 'Accounts: Delete',
        'reports.view' => 'Reports: View',
        'reports.export' => 'Reports: Export',
        'backups.manage' => 'Backups: Manage',
        'settings.manage' => 'Settings: Manage',
        'users.manage' => 'Users: Manage',
    ];

    public const MODEL_MODULES = [
        Customer::class => 'sales',
        Order::class => 'sales',
        CustomerPayment::class => 'sales',
        Supplier::class => 'purchasing',
        Purchase::class => 'purchasing',
        SupplierPayment::class => 'purchasing',
        Product::class => 'inventory',
        Category::class => 'inventory',
        StockMovement::class => 'inventory',
        Account::class => 'accounts',
        Expense::class => 'accounts',
        ExpenseCategory::class => 'accounts',
        TransactionLedger::class => 'accounts',
        self::class => 'users',
        AuditLog::class => 'users',
    ];

    public function isSuperAdmin(): bool
    {
        return $this->effectiveRole() === 'super_admin';
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->is_active === false) {
            return false;
        }

        $permissions = $this->rolePermissions();

        return in_array('*', $permissions, true) || in_array($permission, $permissions, true);
    }

    public static function roleOptions(): array
    {
        if (! self::userRolesTableExists()) {
            return self::ROLES;
        }

        $customRoles = UserRole::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'slug')
            ->all();

        return array_merge(self::ROLES, $customRoles);
    }

    public static function defaultRole(): ?string
    {
        $options = self::roleOptions();

        return array_key_exists('sales_staff', $options)
            ? 'sales_staff'
            : array_key_first($options);
    }

    public function rolePermissions(): array
    {
        $role = $this->effectiveRole();

        if (array_key_exists($role, self::ROLE_PERMISSIONS)) {
            return self::ROLE_PERMISSIONS[$role];
        }

        if (! self::userRolesTableExists()) {
            return [];
        }

        return UserRole::query()
            ->where('slug', $role)
            ->where('is_active', true)
            ->value('permissions') ?? [];
    }

    public function effectiveRole(): string
    {
        return $this->getAttribute('role') ?: 'sales_staff';
    }

    public function canManageUsers(): bool
    {
        return $this->isSuperAdmin() || $this->hasPermission('users.manage');
    }

    public function canEditPayments(): bool
    {
        return $this->isSuperAdmin() || $this->effectiveRole() === 'accountant';
    }

    public function canEditStockMovements(): bool
    {
        return $this->isSuperAdmin() || in_array($this->effectiveRole(), ['manager', 'inventory_staff'], true);
    }

    public function canEditAccounts(): bool
    {
        return $this->isSuperAdmin() || $this->effectiveRole() === 'accountant';
    }

    public function canEditExpenses(): bool
    {
        return $this->isSuperAdmin() || $this->effectiveRole() === 'accountant';
    }

    public function canDeleteSensitiveRecords(): bool
    {
        return $this->isSuperAdmin();
    }

    public function canAccessReports(): bool
    {
        return $this->hasPermission('reports.view');
    }

    public function canExportReports(): bool
    {
        return $this->hasPermission('reports.export');
    }

    public function canManageBackups(): bool
    {
        return $this->isSuperAdmin() || $this->hasPermission('backups.manage');
    }

    public function canManageSettings(): bool
    {
        return $this->isSuperAdmin() || $this->hasPermission('settings.manage');
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class)
            ->withPivot(['role', 'is_default'])
            ->withTimestamps();
    }

    public function accessibleCompanies(): Builder|BelongsToMany
    {
        if ($this->isSuperAdmin()) {
            return Company::query()
                ->where('is_active', true)
                ->orderBy('name');
        }

        return $this->companies()
            ->where('companies.is_active', true)
            ->orderBy('companies.name');
    }

    public function defaultCompany(): ?Company
    {
        return $this->companies()
            ->wherePivot('is_default', true)
            ->where('companies.is_active', true)
            ->first();
    }

    public function canAccessCompany(int $companyId): bool
    {
        if ($this->isSuperAdmin()) {
            return Company::query()
                ->whereKey($companyId)
                ->where('is_active', true)
                ->exists();
        }

        return $this->companies()
            ->whereKey($companyId)
            ->where('companies.is_active', true)
            ->exists();
    }

    public function canPerformModelAbility(string $ability, string $modelClass): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $module = self::MODEL_MODULES[$modelClass] ?? null;

        if (! $module) {
            return false;
        }

        if ($module === 'users') {
            return $this->canManageUsers();
        }

        $permissionAbility = match ($ability) {
            'viewAny', 'view' => 'view',
            'create' => 'create',
            'update' => 'update',
            'delete', 'deleteAny', 'forceDelete', 'forceDeleteAny', 'restore', 'restoreAny' => 'delete',
            default => null,
        };

        if (! $permissionAbility) {
            return false;
        }

        return $this->hasPermission("{$module}.{$permissionAbility}");
    }

    protected static function activeSuperAdminCount(?int $excludingUserId = null): int
    {
        return static::query()
            ->where('role', 'super_admin')
            ->where('is_active', true)
            ->when($excludingUserId, fn ($query) => $query->whereKeyNot($excludingUserId))
            ->count();
    }

    protected static function userRolesTableExists(): bool
    {
        return Schema::hasTable('user_roles');
    }
}
