<?php

namespace Lume\Controllers\Api;

use Lume\Core\Request;
use Lume\Core\Response;
use Lume\Database\QueryBuilder;

class ProductController extends ApiController
{
    public function index(Request $request): Response
    {
        $page = (int) $request->get('page', 1);
        $perPage = (int) $request->get('per_page', 20);
        $category = $request->get('category');
        $search = $request->get('q');
        
        $query = QueryBuilder::table('products')
            ->select('id', 'name', 'slug', 'price', 'compare_price', 'status')
            ->where('status', 'active');
            
        if ($category) {
            $query->where('category_id', $category);
        }
        
        if ($search) {
            $query->where('name', 'LIKE', "%{$search}%");
        }
        
        $products = $query->paginate($perPage, $page);
        
        return $this->paginate($products);
    }
    
    public function show(Request $request, string $id): Response
    {
        $product = QueryBuilder::table('products')
            ->where('id', $id)
            ->where('status', 'active')
            ->first();
            
        if (!$product) {
            return $this->error('Product not found', 404);
        }
        
        $variants = QueryBuilder::table('product_variants')
            ->where('product_id', $id)
            ->where('is_active', 1)
            ->get();
            
        $product['variants'] = $variants;
        
        return $this->success($product);
    }
}
