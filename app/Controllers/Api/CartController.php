<?php

namespace Lume\Controllers\Api;

use Lume\Core\Request;
use Lume\Core\Response;
use Lume\Database\QueryBuilder;

class CartController extends ApiController
{
    public function index(Request $request): Response
    {
        $sessionId = $request->header('X-Session-Id');
        if (!$sessionId) {
            return $this->error('Session ID required', 400);
        }
        
        $items = QueryBuilder::table('cart_items')
            ->leftJoin('products', 'cart_items.product_id', '=', 'products.id')
            ->select('cart_items.*', 'products.name', 'products.price', 'products.image')
            ->where('cart_items.session_id', $sessionId)
            ->get();
            
        return $this->success(['items' => $items]);
    }
    
    public function add(Request $request): Response
    {
        $sessionId = $request->header('X-Session-Id');
        $productId = $request->json('product_id');
        $quantity = (int) $request->json('quantity', 1);
        
        if (!$sessionId || !$productId) {
            return $this->error('Session ID and Product ID required', 400);
        }
        
        // Check if exists
        $existing = QueryBuilder::table('cart_items')
            ->where('session_id', $sessionId)
            ->where('product_id', $productId)
            ->first();
            
        if ($existing) {
            QueryBuilder::table('cart_items')
                ->where('id', $existing['id'])
                ->update(['quantity' => $existing['quantity'] + $quantity]);
        } else {
            QueryBuilder::table('cart_items')->insert([
                'session_id' => $sessionId,
                'product_id' => $productId,
                'quantity' => $quantity
            ]);
        }
        
        return $this->success(null, 'Item added to cart');
    }
}
