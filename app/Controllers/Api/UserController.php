<?php

namespace Axer\Controllers\Api;

use Axer\Core\Request;
use Axer\Core\Response;
use Axer\Database\QueryBuilder;

class UserController extends ApiController
{
    public function profile(Request $request): Response
    {
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            return $this->error('Unauthorized', 401);
        }

        $user = QueryBuilder::table('users')
            ->where('id', $userId)
            ->first();

        if (!$user) {
            return $this->error('User not found', 404);
        }

        unset($user['password_hash']);

        return $this->success($user);
    }
}
