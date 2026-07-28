<?php

/**
 * /theme-assets/{path} — serves a theme's own static files. The route's
 * placeholder regex ([A-Za-z0-9_\-./]+) allows literal ".." segments
 * through (dots and slashes are both in the whitelist), so the traversal
 * guard has to live in the handler itself: realpath() the resolved file
 * and require it to still be inside the theme's assets/ directory.
 */

use Axer\Core\Container;
use Axer\Core\Request;
use Axer\Core\Router;

group('/theme-assets/{path} traversal protection');

// config/routes.php declares `AxerStorefrontPage` at include time — guard
// against a redeclaration fatal if another test file loaded it first.
$router = new Router(new Container());
require BASE_PATH . '/config/routes.php';

function themeAssetRequest(string $uri): \Axer\Core\Response
{
    global $router;

    return $router->dispatch(new Request(['REQUEST_URI' => $uri, 'REQUEST_METHOD' => 'GET']));
}

test('a normal asset request under the active theme succeeds', function () {
    $response = themeAssetRequest('/theme-assets/screenshot.svg');

    assertSame(200, $response->getStatusCode());
});

test('a traversal attempt out of the theme directory is refused', function () {
    $response = themeAssetRequest('/theme-assets/../../../config/.env');

    assertSame(404, $response->getStatusCode());
});

test('a traversal attempt using an absolute-looking path is refused', function () {
    $response = themeAssetRequest('/theme-assets/../../../../etc/passwd');

    assertSame(404, $response->getStatusCode());
});

test('a file outside the extension whitelist is refused even if it exists', function () {
    // theme.json sits right next to assets/ inside the theme directory —
    // reachable by path but not an allowed extension.
    $response = themeAssetRequest('/theme-assets/../theme.json');

    assertSame(404, $response->getStatusCode());
});

test('a missing asset 404s instead of leaking a filesystem error', function () {
    $response = themeAssetRequest('/theme-assets/does-not-exist.css');

    assertSame(404, $response->getStatusCode());
});
