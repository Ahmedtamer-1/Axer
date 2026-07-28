<?php

namespace Axer\Controllers\Admin;

use Axer\Core\Logger;
use Axer\Core\Request;
use Axer\Core\Response;
use Axer\Core\Session;
use Axer\Database\QueryBuilder;
use Axer\Support\Str;

class ProductController extends AdminController
{
    private const STATUSES = ['active', 'draft', 'archived'];

    /**
     * This view is a client-side spreadsheet: search filtering and bulk
     * selection both operate on whatever rows are in the DOM, so the list
     * is loaded in full rather than paginated (a paginated fetch would
     * silently limit search results to the current page with no
     * indication). A cap still guards against an unbounded query.
     */
    private const SPREADSHEET_ROW_LIMIT = 1000;

    public function index(Request $request): Response
    {
        $this->checkAuth($request);

        $products = [];

        try {
            $products = QueryBuilder::table('products')
                ->orderBy('id', 'desc')
                ->limit(self::SPREADSHEET_ROW_LIMIT)
                ->get();

            // The old query wired a correlated subquery straight into
            // select() as a plain string: "(SELECT url FROM ... ) as
            // primary_image". select() now backticks every identifier it is
            // given for safety, which would mangle a raw subquery like
            // that, so the lookup is batched here instead — one query for
            // every image on the page rather than one subquery per row.
            //
            // The view reads $p['image_url'], but the original query (and
            // this replacement) aliased it as primary_image — the column
            // the view actually checked was never populated, so the
            // thumbnail column showed the placeholder icon for every row.
            $images = \Axer\Services\CartService::primaryImagesFor(array_column($products, 'id'));

            foreach ($products as $index => $product) {
                $products[$index]['image_url'] = $images[(int) $product['id']] ?? null;
            }
        } catch (\Throwable $e) {
            Logger::warning('Could not load products: ' . $e->getMessage());
        }

        return $this->renderAdmin('products/index', [
            'title' => 'Products',
            'products' => $products,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->checkAuth($request);
        $error = null;

        if ($request->method() === 'POST') {
            $name = $request->string('name');
            // Str::slug transliterates and falls back to a random suffix
            // when the name has no ASCII-representable characters at all
            // (a pure-Arabic product name), instead of collapsing to an
            // empty or dash-only slug.
            $slug = $request->string('slug') !== ''
                ? Str::slug($request->string('slug'))
                : Str::slugOrFallback($name, 'product');
            $description = $request->post('description');
            $price = max(0.0, $request->float('price'));
            $stock = max(0, $request->int('stock'));
            $status = $request->string('status', 'draft');
            $status = in_array($status, self::STATUSES, true) ? $status : 'draft';

            if ($name === '') {
                $error = 'Product name is required.';
            } else {
                try {
                    $existing = QueryBuilder::table('products')->where('slug', $slug)->first();

                    if ($existing !== null) {
                        $slug .= '-' . substr(bin2hex(random_bytes(3)), 0, 6);
                    }

                    $productId = QueryBuilder::table('products')->insertGetId([
                        'name' => $name,
                        'slug' => $slug,
                        'description' => $description,
                        'price' => $price,
                        'stock' => $stock,
                        'status' => $status,
                    ]);

                    $imageUrl = $request->post('image_url');

                    if (!empty($imageUrl)) {
                        QueryBuilder::table('product_images')->insert([
                            'product_id' => $productId,
                            'url' => $imageUrl,
                            'is_primary' => 1,
                        ]);
                    }

                    Session::flash('success', 'Product created.');

                    return $this->redirect('/admin/products/edit/' . $productId);
                } catch (\Throwable $e) {
                    Logger::exception($e);
                    $error = 'Could not create the product. Please try again.';
                }
            }
        }

        return $this->renderAdmin('products/create', [
            'title' => 'Create Product',
            'error' => $error,
        ]);
    }

    public function edit(Request $request, string $id): Response
    {
        $this->checkAuth($request);
        $error = null;
        $productId = (int) $id;

        $product = QueryBuilder::table('products')->where('id', $productId)->first();

        if ($product === null) {
            Session::flash('error', 'That product no longer exists.');

            return $this->redirect('/admin/products');
        }

        $variants = QueryBuilder::table('product_variants')->where('product_id', $productId)->get();

        $images = QueryBuilder::table('product_images')
            ->where('product_id', $productId)
            ->orderBy('sort_order', 'ASC')
            ->get();

        $primaryImage = null;

        foreach ($images as $img) {
            if (!empty($img['is_primary'])) {
                $primaryImage = $img;
                break;
            }
        }

        if ($request->method() === 'POST') {
            $action = $request->string('action', 'update');

            $response = match ($action) {
                'add_variant' => $this->addVariant($request, $productId),
                'delete_variant' => $this->deleteVariant($request, $productId),
                'assign_image' => $this->assignImage($request, $productId, $images !== []),
                'delete_image' => $this->deleteImage($request, $productId),
                'update' => null, // handled below so $error can be shown inline
                default => $this->redirect('/admin/products/edit/' . $productId),
            };

            if ($response !== null) {
                return $response;
            }

            if ($action === 'update') {
                $name = $request->string('name');
                $slug = $request->string('slug') !== '' ? Str::slug($request->string('slug')) : $product['slug'];
                $description = $request->post('description');
                $price = max(0.0, $request->float('price'));
                $stock = max(0, $request->int('stock'));
                $status = $request->string('status', 'draft');
                $status = in_array($status, self::STATUSES, true) ? $status : 'draft';

                if ($name === '') {
                    $error = 'Product name is required.';
                } else {
                    try {
                        QueryBuilder::transaction(function () use ($productId, $name, $slug, $description, $price, $stock, $status, $request) {
                            QueryBuilder::table('products')->where('id', $productId)->update([
                                'name' => $name,
                                'slug' => $slug,
                                'description' => $description,
                                'price' => $price,
                                'stock' => $stock,
                                'status' => $status,
                            ]);

                            $this->syncVariants($request, $productId);
                        });

                        Session::flash('success', 'Product updated.');

                        return $this->redirect('/admin/products/edit/' . $productId);
                    } catch (\Throwable $e) {
                        Logger::exception($e);
                        $error = 'Could not update the product. Please try again.';
                    }
                }
            }
        }

        return $this->renderAdmin('products/edit', [
            'title' => 'Edit Product',
            'product' => $product,
            'primaryImage' => $primaryImage,
            'images' => $images,
            'variants' => $variants,
            'error' => $error,
        ]);
    }

    protected function addVariant(Request $request, int $productId): Response
    {
        $colorName = $request->string('color_name');
        $colorHex = $request->string('color_hex');
        $size = $request->string('size');

        if ($colorName !== '' || $size !== '') {
            QueryBuilder::table('product_variants')->insert([
                'product_id' => $productId,
                'color_name' => $colorName ?: null,
                'color_hex' => $colorHex ?: null,
                'size' => $size ?: null,
                'stock' => max(0, $request->int('variant_stock')),
                'price_override' => $request->post('price_override') !== null && $request->post('price_override') !== ''
                    ? $request->float('price_override')
                    : null,
            ]);
        }

        return $this->redirect('/admin/products/edit/' . $productId);
    }

    protected function deleteVariant(Request $request, int $productId): Response
    {
        $variantId = $request->int('variant_id');

        if ($variantId > 0) {
            // Scoped to product_id so one product cannot delete another's
            // variant by guessing an id.
            QueryBuilder::table('product_variants')
                ->where('id', $variantId)
                ->where('product_id', $productId)
                ->delete();
        }

        return $this->redirect('/admin/products/edit/' . $productId);
    }

    protected function assignImage(Request $request, int $productId, bool $hasExistingImages): Response
    {
        $imageUrl = $request->post('image_url');

        if (!empty($imageUrl)) {
            $variantId = $request->int('variant_id');

            QueryBuilder::table('product_images')->insert([
                'product_id' => $productId,
                'variant_id' => $variantId > 0 ? $variantId : null,
                'url' => $imageUrl,
                'is_primary' => $hasExistingImages ? 0 : 1,
            ]);
        }

        return $this->redirect('/admin/products/edit/' . $productId);
    }

    protected function deleteImage(Request $request, int $productId): Response
    {
        $imageId = $request->int('image_id');

        if ($imageId > 0) {
            QueryBuilder::table('product_images')
                ->where('id', $imageId)
                ->where('product_id', $productId)
                ->delete();
        }

        return $this->redirect('/admin/products/edit/' . $productId);
    }

    /**
     * Apply the variant grid payload from the builder UI.
     *
     * Runs inside the caller's transaction. Kept as individual statements
     * (rather than one batched insert) because each row can be either an
     * insert or an update depending on whether its id is a client-side
     * temp id — but every write happens as one committed unit instead of
     * each row being its own auto-committed statement, so a failure
     * partway through no longer leaves the variant table half-updated.
     */
    protected function syncVariants(Request $request, int $productId): void
    {
        $payloadJson = $request->post('variants_payload');

        if (empty($payloadJson)) {
            return;
        }

        $payload = json_decode((string) $payloadJson, true);

        if (!is_array($payload) || !isset($payload['options'], $payload['variants'])) {
            return;
        }

        QueryBuilder::table('products')->where('id', $productId)->update([
            'options_schema' => json_encode($payload['options'], JSON_UNESCAPED_UNICODE),
        ]);

        $existingIds = QueryBuilder::table('product_variants')->where('product_id', $productId)->pluck('id');
        $keptIds = [];

        foreach ($payload['variants'] as $v) {
            $isTemp = str_starts_with((string) ($v['id'] ?? ''), 'temp_');

            $data = [
                'product_id' => $productId,
                'option1_value' => $v['option1_value'] ?: null,
                'option2_value' => $v['option2_value'] ?: null,
                'option3_value' => $v['option3_value'] ?: null,
                'price_override' => ($v['price_override'] ?? '') !== '' ? $v['price_override'] : null,
                'compare_price' => ($v['compare_price'] ?? '') !== '' ? $v['compare_price'] : null,
                'sku' => $v['sku'] ?: null,
                'barcode' => $v['barcode'] ?: null,
                'stock' => (int) ($v['stock'] ?? 0),
                'weight' => ($v['weight'] ?? '') !== '' ? $v['weight'] : null,
                'image' => $v['image'] ?: null,
                'color_hex' => $v['color_hex'] ?: null,
            ];

            if ($isTemp) {
                QueryBuilder::table('product_variants')->insert($data);
                continue;
            }

            $variantId = (int) $v['id'];

            // Scoped to product_id: the variant id in the payload is
            // client-supplied and was never checked against the product
            // being edited.
            QueryBuilder::table('product_variants')
                ->where('id', $variantId)
                ->where('product_id', $productId)
                ->update($data);

            $keptIds[] = $variantId;
        }

        $toDelete = array_diff($existingIds, $keptIds);

        if ($toDelete !== []) {
            QueryBuilder::table('product_variants')
                ->where('product_id', $productId)
                ->whereIn('id', array_values($toDelete))
                ->delete();
        }
    }

    public function delete(Request $request, string $id): Response
    {
        $this->checkAuth($request);

        try {
            QueryBuilder::table('products')->where('id', (int) $id)->delete();
            Session::flash('success', 'Product deleted.');
        } catch (\Throwable $e) {
            Logger::exception($e);
            Session::flash('error', 'Could not delete the product.');
        }

        return $this->redirect('/admin/products');
    }
}
