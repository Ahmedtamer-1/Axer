<?php

/**
 * QueryBuilder behaviour, executed against an in-memory SQLite database so
 * the generated SQL is actually run rather than just string-compared.
 */

use Axer\Database\Connection;
use Axer\Database\QueryBuilder;

group('QueryBuilder');

$pdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$pdo->exec('CREATE TABLE api_tokens (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    token_hash TEXT,
    expires_at TEXT NULL,
    last_used_at TEXT NULL
)');

$pdo->exec('CREATE TABLE products (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT,
    slug TEXT,
    price REAL DEFAULT 0,
    stock INTEGER DEFAULT 0,
    status TEXT DEFAULT "draft",
    sort_order INTEGER DEFAULT 0,
    deleted_at TEXT NULL
)');

Connection::setInstance($pdo);

// Seed: one token that never expires, one still valid, one long expired.
$pdo->exec("INSERT INTO api_tokens (token_hash, expires_at) VALUES
    ('never', NULL),
    ('valid', '2999-01-01 00:00:00'),
    ('stale', '2000-01-01 00:00:00')");

$pdo->exec("INSERT INTO products (name, slug, price, stock, status, sort_order) VALUES
    ('Alpha', 'alpha', 10.50, 5, 'active', 2),
    ('Beta',  'beta',  20.00, 0, 'active', 1),
    ('Gamma', 'gamma', 30.25, 3, 'draft',  3)");

test('binds a real NULL instead of collapsing the operator', function () {
    // where('col', '=', null) used to become where('col', '=', '=').
    $rows = QueryBuilder::table('api_tokens')->where('expires_at', '=', null)->get();

    assertSame(1, count($rows));
    assertSame('never', $rows[0]['token_hash']);
});

test('where() with an explicit operator keeps that operator', function () {
    $rows = QueryBuilder::table('products')->where('price', '>', 15)->get();

    assertSame(2, count($rows));
});

test('two-argument where() still defaults to equality', function () {
    $rows = QueryBuilder::table('products')->where('slug', 'beta')->get();

    assertSame(1, count($rows));
    assertSame('Beta', $rows[0]['name']);
});

test('where() accepts a falsy value without treating it as missing', function () {
    $rows = QueryBuilder::table('products')->where('stock', 0)->get();

    assertSame(1, count($rows));
    assertSame('Beta', $rows[0]['name']);
});

test('whereGroup keeps OR from leaking across an AND', function () {
    // The real AuthMiddleware query: a token matches when its hash matches
    // AND (it never expires OR it expires in the future).
    $now = date('Y-m-d H:i:s');

    $match = static fn(string $hash) => QueryBuilder::table('api_tokens')
        ->where('token_hash', $hash)
        ->whereGroup(static function (QueryBuilder $q) use ($now) {
            $q->whereNull('expires_at')->orWhere('expires_at', '>', $now);
        })
        ->first();

    assertTrue($match('never') !== null, 'a non-expiring token must authenticate');
    assertTrue($match('valid') !== null, 'an unexpired token must authenticate');
    assertSame(null, $match('stale'), 'an expired token must be rejected');
    assertSame(null, $match('nope'), 'an unknown token must be rejected');
});

test('without grouping, a bare orWhere would match the wrong rows', function () {
    // Demonstrates the precedence trap the grouped version avoids:
    // token_hash = 'nope' AND expires_at > now OR expires_at IS NULL
    // still returns the non-expiring row.
    $leaky = QueryBuilder::table('api_tokens')
        ->where('token_hash', 'nope')
        ->where('expires_at', '>', date('Y-m-d H:i:s'))
        ->orWhereNull('expires_at')
        ->get();

    assertSame(1, count($leaky), 'ungrouped OR leaks — this is why whereGroup exists');
});

test('orderBy rejects an injected column name', function () {
    assertThrows(
        static fn() => QueryBuilder::table('products')->orderBy('price; DROP TABLE products--')->get(),
        'orderBy must refuse anything that is not a plain identifier'
    );

    // The table survived.
    assertSame(3, QueryBuilder::table('products')->count());
});

test('orderBy only allows ASC or DESC', function () {
    $rows = QueryBuilder::table('products')->orderBy('price', 'desc')->get();

    assertSame('Gamma', $rows[0]['name']);

    // Anything that is not DESC falls back to ASC rather than reaching SQL.
    $rows = QueryBuilder::table('products')->orderBy('price', '; DROP TABLE products')->get();

    assertSame('Alpha', $rows[0]['name']);
});

test('whereIn with an empty array does not produce invalid SQL', function () {
    // `IN ()` is a syntax error; this used to blow up at runtime.
    $rows = QueryBuilder::table('products')->whereIn('id', [])->get();

    assertSame(0, count($rows));
});

test('whereNotIn with an empty array matches everything', function () {
    $rows = QueryBuilder::table('products')->whereNotIn('id', [])->get();

    assertSame(3, count($rows));
});

test('whereIn binds values in the right order alongside other clauses', function () {
    $rows = QueryBuilder::table('products')
        ->where('status', 'active')
        ->whereIn('slug', ['alpha', 'gamma'])
        ->where('price', '>', 1)
        ->get();

    assertSame(1, count($rows));
    assertSame('Alpha', $rows[0]['name']);
});

test('first() does not permanently pin LIMIT 1 on the builder', function () {
    $query = QueryBuilder::table('products')->where('status', 'active');

    $one = $query->first();
    assertTrue($one !== null);

    // Reusing the same builder must still return every matching row.
    assertSame(2, count($query->get()));
});

test('count() leaves the builder reusable', function () {
    $query = QueryBuilder::table('products')->where('status', 'active');

    assertSame(2, $query->count());
    assertSame(2, count($query->get()), 'count() must not clobber the select list');
});

test('paginate reports a sane last_page and does not mutate the total', function () {
    $page = QueryBuilder::table('products')->orderBy('id')->paginate(2, 1);

    assertSame(3, $page['total']);
    assertSame(2, $page['last_page']);
    assertSame(2, count($page['data']));
    assertSame(1, $page['from']);
    assertSame(2, $page['to']);
    assertTrue($page['has_more']);
});

test('paginate on an empty result still reports last_page 1', function () {
    $page = QueryBuilder::table('products')->where('status', 'archived')->paginate(20, 1);

    assertSame(0, $page['total']);
    assertSame(1, $page['last_page'], 'ceil(0/20) is 0, which is not a valid page number');
    assertFalse($page['has_more']);
});

test('delete without a where clause is refused', function () {
    assertThrows(
        static fn() => QueryBuilder::table('products')->delete(),
        'an unqualified DELETE would empty the table'
    );

    assertSame(3, QueryBuilder::table('products')->count());
});

test('keyBy turns one query into a lookup table', function () {
    $bySlug = QueryBuilder::table('products')->keyBy('slug');

    assertSame('Alpha', $bySlug['alpha']['name']);
    assertSame('Gamma', $bySlug['gamma']['name']);
});

test('pluck returns a flat column', function () {
    $slugs = QueryBuilder::table('products')->orderBy('id')->pluck('slug');

    assertSame(['alpha', 'beta', 'gamma'], $slugs);
});

test('whereLike escapes wildcards in the search term', function () {
    $pdo = Connection::resolve();
    $pdo->exec("INSERT INTO products (name, slug) VALUES ('100% Cotton', 'cotton'), ('100 Percent', 'percent')");

    $rows = QueryBuilder::table('products')->whereLike('name', '100%')->get();

    // Unescaped, '%100%%' would also match '100 Percent'.
    assertSame(1, count($rows));
    assertSame('100% Cotton', $rows[0]['name']);

    $pdo->exec("DELETE FROM products WHERE slug IN ('cotton', 'percent')");
});

test('transaction rolls back on a TypeError, not just an Exception', function () {
    $before = QueryBuilder::table('products')->count();

    try {
        QueryBuilder::transaction(static function (PDO $pdo) {
            $pdo->exec("INSERT INTO products (name, slug) VALUES ('Ghost', 'ghost')");
            throw new \TypeError('boom');
        });
    } catch (\TypeError $e) {
        // expected
    }

    assertSame($before, QueryBuilder::table('products')->count(), 'the insert must have been rolled back');
    assertFalse(Connection::resolve()->inTransaction(), 'the connection must not be left mid-transaction');
});

test('insertMany writes every row in one statement', function () {
    $written = QueryBuilder::table('products')->insertMany([
        ['name' => 'Bulk A', 'slug' => 'bulk-a', 'price' => 1.0],
        ['name' => 'Bulk B', 'slug' => 'bulk-b', 'price' => 2.0],
    ]);

    assertSame(2, $written);
    assertSame(2, QueryBuilder::table('products')->whereIn('slug', ['bulk-a', 'bulk-b'])->count());

    QueryBuilder::table('products')->whereIn('slug', ['bulk-a', 'bulk-b'])->delete();
});

test('sum aggregates without tripping identifier quoting', function () {
    $total = QueryBuilder::table('products')->where('status', 'active')->sum('price');

    assertSame(30.5, $total);
});

Connection::setInstance(null);
