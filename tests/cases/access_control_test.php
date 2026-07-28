<?php

/**
 * Admin role tiers (Axer\Auth\Roles) — AdminController::checkAuth() used
 * to only check whether $_SESSION['admin_user'] was set, so `admin` and
 * `superadmin` were fully interchangeable everywhere.
 */

use Axer\Auth\Roles;

group('Roles');

test('an anonymous session has no role', function () {
    unset($_SESSION['admin_user']);

    assertSame(null, Roles::current());
    assertFalse(Roles::atLeast('admin'));
});

test('atLeast() honours the tier order: customer < admin < superadmin', function () {
    $_SESSION['admin_user'] = ['role' => 'admin'];

    assertTrue(Roles::atLeast('customer'));
    assertTrue(Roles::atLeast('admin'));
    assertFalse(Roles::atLeast('superadmin'));
});

test('a superadmin satisfies every lower tier', function () {
    $_SESSION['admin_user'] = ['role' => 'superadmin'];

    assertTrue(Roles::atLeast('customer'));
    assertTrue(Roles::atLeast('admin'));
    assertTrue(Roles::atLeast('superadmin'));
});

test('is() matches an explicit role list', function () {
    $_SESSION['admin_user'] = ['role' => 'admin'];

    assertTrue(Roles::is('admin', 'superadmin'));
    assertFalse(Roles::is('superadmin'));
});

test('an unrecognised role string is treated as the lowest tier, not trusted', function () {
    $_SESSION['admin_user'] = ['role' => 'something-injected'];

    assertFalse(Roles::atLeast('admin'));
});

unset($_SESSION['admin_user']);
