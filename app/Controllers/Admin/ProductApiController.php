<?php

namespace Axer\Controllers\Admin;

use Axer\Core\Logger;
use Axer\Core\Request;
use Axer\Core\Response;
use Axer\Database\QueryBuilder;
use Axer\Support\Str;

class ProductApiController extends AdminController
{
    private const STATUSES = ['active', 'draft', 'archived'];

    public function updateField(Request $request, string $id): Response
    {
        $this->checkAuth($request);

        $data = $request->json();

        if ($data === []) {
            $data = $request->post();
        }

        $allowedFields = ['name', 'sku', 'price', 'compare_price', 'stock', 'status', 'brand'];
        $update = [];

        foreach ($data as $key => $val) {
            if (!in_array($key, $allowedFields, true)) {
                continue;
            }

            // The old handler wrote whatever the client sent straight into
            // an ENUM column; a stray value there is a driver error under
            // the strict SQL mode the connection now sets.
            if ($key === 'status') {
                if (!in_array($val, self::STATUSES, true)) {
                    continue;
                }
            } elseif (in_array($key, ['price', 'compare_price'], true)) {
                if ($val !== null && $val !== '' && !is_numeric($val)) {
                    continue;
                }
                $val = ($val === null || $val === '') ? null : max(0, (float) $val);
            } elseif ($key === 'stock') {
                $val = max(0, (int) $val);
            }

            $update[$key] = $val;
        }

        if ($update === []) {
            return Response::json(['success' => false, 'message' => 'No valid fields provided.'], 400);
        }

        try {
            $affected = QueryBuilder::table('products')->where('id', (int) $id)->update($update);

            return Response::json(['success' => true, 'updated' => $update, 'changed' => $affected > 0]);
        } catch (\Throwable $e) {
            Logger::exception($e);

            return Response::json(['success' => false, 'message' => 'Could not save that change.'], 500);
        }
    }

    public function quickCreate(Request $request): Response
    {
        $this->checkAuth($request);

        $name = trim((string) ($request->json('name') ?? $request->post('name') ?? 'New Product'));
        $slug = Str::slugOrFallback($name, 'product') . '-' . substr(bin2hex(random_bytes(3)), 0, 6);

        try {
            $id = QueryBuilder::table('products')->insertGetId([
                'name' => $name !== '' ? $name : 'New Product',
                'slug' => $slug,
                'price' => 0.00,
                'stock' => 0,
                'status' => 'draft',
            ]);

            return Response::json(['success' => true, 'id' => $id, 'name' => $name, 'slug' => $slug]);
        } catch (\Throwable $e) {
            Logger::exception($e);

            return Response::json(['success' => false, 'message' => 'Could not create the product.'], 500);
        }
    }

    public function bulkDelete(Request $request): Response
    {
        $this->checkAuth($request);

        $ids = $request->json('ids') ?? [];

        if (!is_array($ids) || $ids === []) {
            return Response::json(['success' => false, 'message' => 'No product IDs specified.'], 400);
        }

        $ids = array_values(array_unique(array_map('intval', $ids)));

        try {
            // One statement for the whole selection instead of one DELETE
            // per row.
            $count = QueryBuilder::table('products')->whereIn('id', $ids)->delete();

            return Response::json(['success' => true, 'count' => $count]);
        } catch (\Throwable $e) {
            Logger::exception($e);

            return Response::json(['success' => false, 'message' => 'Could not delete the selected products.'], 500);
        }
    }
}
