<?php

namespace Axer\Controllers\Api;

use Axer\Core\Logger;
use Axer\Core\RateLimiter;
use Axer\Core\Request;
use Axer\Core\Response;
use Axer\Database\QueryBuilder;
use Axer\Services\AccountService;

class AuthController extends ApiController
{
    private const MAX_LOGIN_ATTEMPTS = 10;
    private const LOCKOUT_SECONDS = 900;

    public function login(Request $request): Response
    {
        $email = strtolower(trim((string) ($request->json('email') ?? '')));
        $password = (string) ($request->json('password') ?? '');

        if ($email === '' || $password === '') {
            return $this->error('Email and password are required', 400);
        }

        // This endpoint had no throttling at all.
        $limiter = new RateLimiter();
        $key = 'api-login:' . RateLimiter::clientIp();

        if ($limiter->attempts($key, self::LOCKOUT_SECONDS) >= self::MAX_LOGIN_ATTEMPTS) {
            return $this->error('Too many attempts. Please try again later.', 429);
        }

        $user = AccountService::attempt($email, $password);

        if ($user === null) {
            $limiter->hit($key, self::LOCKOUT_SECONDS);

            return $this->error('Invalid credentials', 401);
        }

        $limiter->clear($key);

        $tokenStr = bin2hex(random_bytes(32));

        try {
            QueryBuilder::table('api_tokens')->insert([
                'user_id' => $user['id'],
                'token_hash' => hash('sha256', $tokenStr),
                'name' => 'API Login',
                'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
            ]);
        } catch (\Throwable $e) {
            Logger::exception($e);

            return $this->error('Could not complete sign-in. Please try again.', 500);
        }

        return $this->success(['token' => $tokenStr, 'user' => $user], 'Login successful');
    }

    public function register(Request $request): Response
    {
        $email = (string) ($request->json('email') ?? '');
        $password = (string) ($request->json('password') ?? '');
        $name = trim((string) ($request->json('name') ?? ''));

        $result = AccountService::register($email, $password, $name);

        if (!$result['ok']) {
            return $this->error($result['message'], 400);
        }

        return $this->success(['id' => $result['user_id']], $result['message'], 201);
    }
}
