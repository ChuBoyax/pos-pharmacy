<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'label', 'description', 'permissions', 'menus'])]
class Role extends Model
{
    public const SUPERADMIN = 'superadmin';

    /**
     * Everything a role can be granted, grouped the way the roles screen shows it.
     */
    public const PERMISSIONS = [
        'Sales' => [
            'pos.sell' => 'Ring up sales at the point of sale',
            'sales.view_all' => "See every cashier's transactions",
            'sales.void' => 'Void completed sales and return the stock',
        ],
        'Inventory' => [
            'medicines.manage' => 'Add, edit and delete medicines and categories',
            'stock.manage' => 'Receive stock and adjust batches',
            'suppliers.manage' => 'Manage suppliers and compare their prices',
        ],
        'Management' => [
            'reports.view' => 'View sales and inventory reports',
            'users.manage' => 'Create and manage staff accounts',
            'settings.manage' => 'Change store and receipt settings',
        ],
    ];

    /**
     * Only the super admin has these; they can never be granted to another role.
     */
    public const RESERVED = ['roles.manage'];

    /**
     * Sidebar menus a role can be shown, keyed by route name: [label, permissions that also
     * unlock it (any one; empty = none needed)]. A menu appears only when both allow it.
     */
    public const MENUS = [
        'dashboard' => ['Dashboard', ''],
        'pos' => ['Point of Sale', 'pos.sell'],
        'transactions' => ['Transactions', 'pos.sell,sales.view_all'],
        'medicines' => ['Medicines', 'medicines.manage'],
        'stock' => ['Stock', 'stock.manage'],
        'suppliers' => ['Suppliers', 'suppliers.manage'],
        'reports' => ['Reports', 'reports.view'],
        'users' => ['Users', 'users.manage'],
        'settings' => ['Settings', ''],
    ];

    protected function casts(): array
    {
        return ['permissions' => 'array', 'menus' => 'array'];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'role', 'name');
    }

    public function isSuperAdmin(): bool
    {
        return $this->name === self::SUPERADMIN;
    }

    /**
     * @return array<int, string>
     */
    public static function grantable(): array
    {
        return array_merge(...array_map('array_keys', array_values(self::PERMISSIONS)));
    }

    /**
     * @return array<int, string>
     */
    public static function allPermissions(): array
    {
        return [...self::grantable(), ...self::RESERVED];
    }
}
