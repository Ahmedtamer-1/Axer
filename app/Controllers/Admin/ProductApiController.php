<?php

namespace Axer\Controllers\Admin;

use Axer\Core\Request;
use Axer\Core\Response;
use Axer\Database\QueryBuilder;

class ProductApiController extends AdminController
{
    public function updateField(Request $request, string $id): Response
    {
        $this->checkAuth($request);

        $data = $request->json();
        if (empty($data)) {
            $data = $request->post();
        }

        $allowedFields = ['name', 'sku', 'price', 'compare_price', 'stock', 'status', 'brand'];
        $update = [];

        foreach ($data as $key => $val) {
            if (in_array($key, $allowedFields)) {
                $update[$key] = $val;
            }
        }

        if (empty($update)) {
            return Response::json(['success' => false, 'message' => 'No valid fields provided'], 400);
        }

        try {
            QueryBuilder::table('products')->where('id', $id)->update($update);
            return Response::json(['success' => true, 'updated' => $update]);
        } catch (\Exception $e) {
            return Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function quickCreate(Request $request): Response
    {
        $this->checkAuth($request);

        $name = trim($request->json('name') ?? $request->post('name') ?? 'New Product');
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name))) . '-' . time();

        try {
            $id = QueryBuilder::table('products')->insert([
                'name' => $name,
                'slug' => $slug,
                'price' => 0.00,
                'stock' => 0,
                'status' => 'draft',
                'created_at' => date('Y-m-d H:i:s')
            ]);

            return Response::json(['success' => true, 'id' => $id, 'name' => $name, 'slug' => $slug]);
        } catch (\Exception $e) {
            return Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function bulkDelete(Request $request): Response
    {
        $this->checkAuth($request);

        $ids = $request->json('ids') ?? [];
        if (empty($ids)) {
            return Response::json(['success' => false, 'message' => 'No product IDs specified'], 400);
        }

        try {
            foreach ($ids as $id) {
                QueryBuilder::table('products')->where('id', $id)->delete();
            }
            return Response::json(['success' => true, 'count' => count($ids)]);
        } catch (\Exception $e) {
            return Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
