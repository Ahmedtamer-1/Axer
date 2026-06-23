<?php

namespace Axer\Controllers\Api;

use Axer\Core\Controller;
use Axer\Core\Response;

class ApiController extends Controller
{
    protected function success($data = null, string $message = 'Success', int $code = 200): Response
    {
        return $this->json([
            'success' => true,
            'message' => $message,
            'data' => $data
        ], $code);
    }

    protected function error(string $message = 'Error', int $code = 400, $errors = null): Response
    {
        $response = [
            'success' => false,
            'message' => $message
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return $this->json($response, $code);
    }

    protected function paginate(array $paginatedData): Response
    {
        return $this->success([
            'items' => $paginatedData['data'],
            'pagination' => [
                'total' => $paginatedData['total'],
                'per_page' => $paginatedData['per_page'],
                'current_page' => $paginatedData['current_page'],
                'last_page' => $paginatedData['last_page']
            ]
        ]);
    }
}
