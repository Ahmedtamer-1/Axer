<?php

namespace Axer\Auth;

/**
 * Admin role tiers.
 *
 * The `users.role` enum is `customer < admin < superadmin`, but until now
 * nothing in the codebase ever compared roles — AdminController::checkAuth()
 * only checked whether $_SESSION['admin_user'] was set, so an `admin` and a
 * `superadmin` account were fully interchangeable everywhere.
 */
class Roles
{
    protected const ORDER = ['customer' => 0, 'admin' => 1, 'superadmin' => 2];

    public static function current(): ?string
    {
        $role = $_SESSION['admin_user']['role'] ?? null;

        return is_string($role) ? $role : null;
    }

    public static function is(string ...$roles): bool
    {
        $current = self::current();

        return $current !== null && in_array($current, $roles, true);
    }

    /**
     * True if the logged-in admin's role is $role or higher in the tier.
     * An unrecognised role is treated as the lowest tier.
     */
    public static function atLeast(string $role): bool
    {
        $current = self::current();

        if ($current === null) {
            return false;
        }

        return (self::ORDER[$current] ?? 0) >= (self::ORDER[$role] ?? 0);
    }
}
