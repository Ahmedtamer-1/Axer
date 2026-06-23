<?php

namespace Lume\Controllers\Admin;

use Lume\Core\Request;
use Lume\Core\Response;
use Lume\Database\QueryBuilder;

class ProductController extends AdminController
{
    public function index(Request $request): Response
    {
        $this->checkAuth($request);
        
        $products = [];
        try {
            // Fetch products and their primary images (if any)
            $products = QueryBuilder::table('products')
                ->select('products.*, (SELECT url FROM product_images WHERE product_id = products.id AND is_primary = 1 LIMIT 1) as primary_image')
                ->orderBy('id', 'desc')
                ->get();
        } catch (\Exception $e) {
            // Table might not exist yet
        }

        return $this->renderAdmin('products/index', [
            'title' => 'Products',
            'products' => $products
        ]);
    }

    public function create(Request $request): Response
    {
        $this->checkAuth($request);
        $error = null;

        if ($request->method() === 'POST') {
            $name = $request->post('name');
            $slug = $request->post('slug') ?: strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
            $description = $request->post('description');
            $price = floatval($request->post('price', 0));
            $stock = intval($request->post('stock', 0));
            $status = $request->post('status', 'draft');

            if (empty($name)) {
                $error = "Product name is required.";
            } else {
                try {
                    // Check duplicate slug
                    $existing = QueryBuilder::table('products')->where('slug', $slug)->first();
                    if ($existing) {
                        $slug .= '-' . time();
                    }

                    $productId = QueryBuilder::table('products')->insert([
                        'name' => $name,
                        'slug' => $slug,
                        'description' => $description,
                        'price' => $price,
                        'stock' => $stock,
                        'status' => $status
                    ]);

                    // Handle image assignment
                    $imageUrl = $request->post('image_url');
                    if (!empty($imageUrl)) {
                        QueryBuilder::table('product_images')->insert([
                            'product_id' => $productId,
                            'url' => $imageUrl,
                            'is_primary' => 1
                        ]);
                    }

                    return $this->redirect('/admin/products');
                } catch (\Exception $e) {
                    $error = "Error creating product: " . $e->getMessage();
                }
            }
        }

        return $this->renderAdmin('products/create', [
            'title' => 'Create Product',
            'error' => $error
        ]);
    }

    public function edit(Request $request, string $id): Response
    {
        $this->checkAuth($request);
        $error = null;

        $product = QueryBuilder::table('products')->where('id', $id)->first();
        if (!$product) {
            return $this->redirect('/admin/products');
        }

        // Fetch variants
        $variants = QueryBuilder::table('product_variants')
            ->where('product_id', $id)
            ->get();

        // Fetch all product images
        $images = QueryBuilder::table('product_images')
            ->where('product_id', $id)
            ->orderBy('sort_order', 'ASC')
            ->get();

        $primaryImage = null;
        foreach ($images as $img) {
            if ($img['is_primary']) $primaryImage = $img;
        }

        if ($request->method() === 'POST') {
            $action = $request->post('action', 'update');

            if ($action === 'add_variant') {
                $colorName = $request->post('color_name');
                $colorHex = $request->post('color_hex');
                $size = $request->post('size');
                if (!empty($colorName) || !empty($size)) {
                    QueryBuilder::table('product_variants')->insert([
                        'product_id' => $id,
                        'color_name' => $colorName,
                        'color_hex' => $colorHex,
                        'size' => $size,
                        'stock' => intval($request->post('variant_stock', 0)),
                        'price_override' => $request->post('price_override') ? floatval($request->post('price_override')) : null
                    ]);
                }
                return $this->redirect('/admin/products/edit/' . $id);
            }

            if ($action === 'delete_variant') {
                $variantId = $request->post('variant_id');
                QueryBuilder::table('product_variants')->where('id', $variantId)->delete();
                return $this->redirect('/admin/products/edit/' . $id);
            }

            if ($action === 'assign_image') {
                $imageUrl = $request->post('image_url');
                if (!empty($imageUrl)) {
                    $variantId = $request->post('variant_id');
                    QueryBuilder::table('product_images')->insert([
                        'product_id' => $id,
                        'variant_id' => empty($variantId) ? null : $variantId,
                        'url' => $imageUrl,
                        'is_primary' => empty($images) ? 1 : 0
                    ]);
                }
                return $this->redirect('/admin/products/edit/' . $id);
            }

            if ($action === 'delete_image') {
                $imageId = $request->post('image_id');
                QueryBuilder::table('product_images')->where('id', $imageId)->delete();
                return $this->redirect('/admin/products/edit/' . $id);
            }

            if ($action === 'update') {
                $name = $request->post('name');
                $slug = $request->post('slug') ?: $product['slug'];
                $description = $request->post('description');
                $price = floatval($request->post('price', 0));
                $stock = intval($request->post('stock', 0));
                $status = $request->post('status', 'draft');

                if (empty($name)) {
                    $error = "Product name is required.";
                } else {
                    try {
                        QueryBuilder::table('products')->where('id', $id)->update([
                            'name' => $name,
                            'slug' => $slug,
                            'description' => $description,
                            'price' => $price,
                            'stock' => $stock,
                            'status' => $status
                        ]);
                        return $this->redirect('/admin/products/edit/' . $id);
                    } catch (\Exception $e) {
                        $error = "Error updating product: " . $e->getMessage();
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
            'error' => $error
        ]);
    }

    public function delete(Request $request, string $id): Response
    {
        $this->checkAuth($request);
        try {
            QueryBuilder::table('products')->where('id', $id)->delete();
        } catch (\Exception $e) {}
        
        return $this->redirect('/admin/products');
    }
}
