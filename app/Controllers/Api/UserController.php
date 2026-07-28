<?php

namespace Axer\Controllers\Api;

use Axer\Core\Request;
use Axer\Core\Response;
use Axer\Database\QueryBuilder;

class UserController extends ApiController
{
    public function profile(Request $request): Response
    {
        // AuthMiddleware verifies the bearer token and attaches the
        // authenticated user via Request::setAuth() — but this endpoint
        // checked $_SESSION['user_id'] instead, which a token-based API
        // client never sets. profile() returned 401 for every legitimate
        // request that reached it.
        $userId = $request->userId();

        if ($userId === null) {
            return $this->error('Unauthorized', 401);
        }

        $user = QueryBuilder::table('users')->where('id', $userId)->first();

        if ($user === null) {
            return $this->error('User not found', 404);
        }

        unset($user['password_hash']);

        return $this->success($user);
    }
}
