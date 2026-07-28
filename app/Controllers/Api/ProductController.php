<?php

namespace Axer\Controllers\Api;

use Axer\Core\Request;
use Axer\Core\Response;
use Axer\Database\QueryBuilder;

class ProductController extends ApiController
{
    public function index(Request $request): Response
    {
        $page = max(1, (int) $request->get('page', 1));
        // Uncapped, a client could request per_page=999999 and force a
        // full table scan back to itself.
        $perPage = max(1, min(100, (int) $request->get('per_page', 20)));
        $category = $request->get('category');
        $search = $request->string('q');

        $query = QueryBuilder::table('products')
            ->select('id', 'name', 'slug', 'price', 'compare_price', 'status')
            ->where('status', 'active');

        if ($category !== null && $category !== '') {
            $query->where('category_id', (int) $category);
        }

        if ($search !== '') {
            $query->whereLike('name', $search);
        }

        return $this->paginate($query->paginate($perPage, $page));
    }

    public function show(Request $request, string $id): Response
    {
        $product = QueryBuilder::table('products')
            ->where('id', (int) $id)
            ->where('status', 'active')
            ->first();

        if ($product === null) {
            return $this->error('Product not found', 404);
        }

        $variants = QueryBuilder::table('product_variants')
            ->where('product_id', (int) $id)
            ->where('is_active', 1)
            ->get();

        $product['variants'] = $variants;

        return $this->success($product);
    }
}
