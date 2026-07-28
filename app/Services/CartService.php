<?php

namespace Axer\Services;

use Axer\Core\Session;
use Axer\Database\QueryBuilder;

/**
 * Server-side authority on what is in the cart and what it costs.
 *
 * Two problems this exists to fix:
 *
 *  1. CheckoutController read the amount to charge straight out of the
 *     POST body (`$request->post('amount', 100)`), so a shopper could set
 *     their own price. Totals are now always recomputed here from the
 *     database.
 *  2. CartController::index() ran three queries per line item (product,
 *     image, variant). A ten-item cart issued thirty-one queries. Every
 *     lookup below is batched into one query per table.
 */
class CartService
{
    public const SESSION_KEY = 'cart';

    /** Defensive ceiling so a single line cannot overflow the total. */
    public const MAX_QUANTITY_PER_LINE = 99;

    /**
     * Resolve the session cart into priced line items.
     *
     * @return array{items: array, subtotal: float, count: int, removed: array}
     */
    public static function resolve(): array
    {
        Session::start();

        $cart = $_SESSION[self::SESSION_KEY] ?? [];

        if (!is_array($cart) || $cart === []) {
            return ['items' => [], 'subtotal' => 0.0, 'count' => 0, 'removed' => []];
        }

        $productIds = [];
        $variantIds = [];

        foreach ($cart as $line) {
            if (!empty($line['product_id'])) {
                $productIds[] = (int) $line['product_id'];
            }
            if (!empty($line['variant_id'])) {
                $variantIds[] = (int) $line['variant_id'];
            }
        }

        $productIds = array_values(array_unique($productIds));
        $variantIds = array_values(array_unique($variantIds));

        if ($productIds === []) {
            return ['items' => [], 'subtotal' => 0.0, 'count' => 0, 'removed' => []];
        }

        // One query per table, regardless of cart size.
        $products = QueryBuilder::table('products')
            ->whereIn('id', $productIds)
            ->where('status', 'active')
            ->keyBy('id');

        $variants = $variantIds === []
            ? []
            : QueryBuilder::table('product_variants')->whereIn('id', $variantIds)->keyBy('id');

        $images = self::primaryImagesFor(array_keys($products));

        $items = [];
        $subtotal = 0.0;
        $count = 0;
        $removed = [];

        foreach ($cart as $key => $line) {
            $productId = (int) ($line['product_id'] ?? 0);
            $product = $products[$productId] ?? null;

            // A product that was deleted or unpublished after it was added
            // used to linger in the cart as a phantom line.
            if ($product === null) {
                $removed[] = $key;
                continue;
            }

            $variant = null;

            if (!empty($line['variant_id'])) {
                $variant = $variants[(int) $line['variant_id']] ?? null;

                // Reject a variant that belongs to a different product —
                // the variant id came from the client and was never checked.
                if ($variant !== null && (int) $variant['product_id'] !== $productId) {
                    $variant = null;
                }
            }

            $quantity = max(1, min(self::MAX_QUANTITY_PER_LINE, (int) ($line['quantity'] ?? 1)));

            $price = ($variant !== null && $variant['price_override'] !== null && $variant['price_override'] !== '')
                ? (float) $variant['price_override']
                : (float) $product['price'];

            $lineTotal = round($price * $quantity, 2);
            $subtotal += $lineTotal;
            $count += $quantity;

            $items[] = [
                'key' => $key,
                'product_id' => $productId,
                'variant_id' => $variant['id'] ?? null,
                'name' => $product['name'],
                'slug' => $product['slug'],
                'sku' => $variant['sku'] ?? $product['sku'] ?? null,
                'variant_name' => $variant !== null ? self::variantLabel($variant) : null,
                'price' => $price,
                'formatted_price' => number_format($price, 2),
                'quantity' => $quantity,
                'total' => $lineTotal,
                'formatted_total' => number_format($lineTotal, 2),
                'image_url' => $images[$productId] ?? self::placeholderImage(),
                'stock' => (int) ($variant['stock'] ?? $product['stock'] ?? 0),
                'track_stock' => (bool) ($product['track_stock'] ?? 1),
            ];
        }

        // Drop lines whose product no longer exists.
        if ($removed !== []) {
            foreach ($removed as $key) {
                unset($_SESSION[self::SESSION_KEY][$key]);
            }
        }

        return [
            'items' => $items,
            'subtotal' => round($subtotal, 2),
            'count' => $count,
            'removed' => $removed,
        ];
    }

    /**
     * Primary image per product, in a single query.
     */
    public static function primaryImagesFor(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $rows = QueryBuilder::table('product_images')
            ->whereIn('product_id', array_map('intval', $productIds))
            ->orderBy('is_primary', 'desc')
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $images = [];

        foreach ($rows as $row) {
            // First row per product wins thanks to the ordering above.
            $images[(int) $row['product_id']] ??= $row['url'];
        }

        return $images;
    }

    /**
     * Number of units in the cart, for the header badge.
     */
    public static function count(): int
    {
        Session::start();

        $cart = $_SESSION[self::SESSION_KEY] ?? [];

        if (!is_array($cart)) {
            return 0;
        }

        $total = 0;

        foreach ($cart as $line) {
            $total += max(0, (int) ($line['quantity'] ?? 0));
        }

        return $total;
    }

    /**
     * Add a line, validating that the product (and variant) really exist
     * and are purchasable. None of this was checked before: any integer
     * could be pushed into the cart.
     *
     * @return array{ok: bool, message: string, count: int}
     */
    public static function add(int $productId, $variantId = null, int $quantity = 1): array
    {
        Session::start();

        $quantity = max(1, min(self::MAX_QUANTITY_PER_LINE, $quantity));

        $product = QueryBuilder::table('products')
            ->where('id', $productId)
            ->where('status', 'active')
            ->first();

        if ($product === null) {
            return ['ok' => false, 'message' => 'That product is no longer available.', 'count' => self::count()];
        }

        $variant = null;

        if ($variantId !== null && $variantId !== '' && $variantId !== '0') {
            $variant = QueryBuilder::table('product_variants')
                ->where('id', (int) $variantId)
                ->where('product_id', $productId)
                ->first();

            if ($variant === null) {
                return [
                    'ok' => false,
                    'message' => 'Please choose a valid option for this product.',
                    'count' => self::count(),
                ];
            }
        }

        $key = $productId . '_' . ($variant['id'] ?? 0);
        $existing = (int) ($_SESSION[self::SESSION_KEY][$key]['quantity'] ?? 0);
        $desired = min(self::MAX_QUANTITY_PER_LINE, $existing + $quantity);

        // Respect stock when the product is tracked.
        if (!empty($product['track_stock'])) {
            $available = (int) ($variant['stock'] ?? $product['stock'] ?? 0);

            if ($available <= 0) {
                return ['ok' => false, 'message' => 'This item is out of stock.', 'count' => self::count()];
            }

            if ($desired > $available) {
                $desired = $available;

                if ($desired <= $existing) {
                    return [
                        'ok' => false,
                        'message' => "Only {$available} left in stock — your cart already has the maximum.",
                        'count' => self::count(),
                    ];
                }
            }
        }

        $_SESSION[self::SESSION_KEY][$key] = [
            'product_id' => $productId,
            'variant_id' => $variant['id'] ?? null,
            'quantity' => $desired,
        ];

        return ['ok' => true, 'message' => 'Added to your cart.', 'count' => self::count()];
    }

    public static function updateQuantity(string $key, int $quantity): void
    {
        Session::start();

        if (!isset($_SESSION[self::SESSION_KEY][$key])) {
            return;
        }

        if ($quantity <= 0) {
            unset($_SESSION[self::SESSION_KEY][$key]);
            return;
        }

        $_SESSION[self::SESSION_KEY][$key]['quantity'] = min(self::MAX_QUANTITY_PER_LINE, $quantity);
    }

    public static function remove(string $key): void
    {
        Session::start();
        unset($_SESSION[self::SESSION_KEY][$key]);
    }

    public static function clear(): void
    {
        Session::start();
        unset($_SESSION[self::SESSION_KEY]);
    }

    protected static function variantLabel(array $variant): string
    {
        $labels = array_filter([
            $variant['option1_value'] ?? null,
            $variant['option2_value'] ?? null,
            $variant['option3_value'] ?? null,
        ], static fn($v) => $v !== null && $v !== '');

        if ($labels !== []) {
            return implode(' / ', $labels);
        }

        return (string) ($variant['color_name'] ?: $variant['size'] ?: '');
    }

    /**
     * Local placeholder.
     *
     * The storefront pointed at via.placeholder.com (a service that has
     * since shut down) and a hardcoded Unsplash photo, so missing images
     * rendered as broken ones and leaked a request to a third party.
     */
    public static function placeholderImage(): string
    {
        return '/assets/img/placeholder.svg';
    }
}
