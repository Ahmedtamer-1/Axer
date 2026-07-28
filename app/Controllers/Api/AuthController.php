<?php

namespace Axer\Controllers\Api;

use Axer\Core\Logger;
use Axer\Core\RateLimiter;
use Axer\Core\Request;
use Axer\Core\Response;
use Axer\Database\QueryBuilder;

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

        $user = QueryBuilder::table('users')
            ->where('email', $email)
            ->where('is_active', 1)
            ->first();

        // Always run a verification, even against an unknown account, so
        // response time cannot be used to enumerate registered emails.
        $hash = $user['password_hash'] ?? '$2y$12$usesomesillystringfoeu1f7.4mnPYsl8oCVGvJ3.9ycCCbfW9pi';

        if ($user === null || $hash === null || !password_verify($password, $hash)) {
            $limiter->hit($key, self::LOCKOUT_SECONDS);

            return $this->error('Invalid credentials', 401);
        }

        $limiter->clear($key);

        if (password_needs_rehash($hash, PASSWORD_ARGON2ID)) {
            try {
                QueryBuilder::table('users')->where('id', $user['id'])->update([
                    'password_hash' => password_hash($password, PASSWORD_ARGON2ID),
                ]);
            } catch (\Throwable $e) {
                Logger::warning('Could not rehash password: ' . $e->getMessage());
            }
        }

        $tokenStr = bin2hex(random_bytes(32));

        try {
            QueryBuilder::table('api_tokens')->insert([
                'user_id' => $user['id'],
                'token_hash' => hash('sha256', $tokenStr),
                'name' => 'API Login',
                'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
            ]);

            QueryBuilder::table('users')->where('id', $user['id'])->update([
                'last_login_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            Logger::exception($e);

            return $this->error('Could not complete sign-in. Please try again.', 500);
        }

        unset($user['password_hash']);

        return $this->success(['token' => $tokenStr, 'user' => $user], 'Login successful');
    }

    public function register(Request $request): Response
    {
        $email = strtolower(trim((string) ($request->json('email') ?? '')));
        $password = (string) ($request->json('password') ?? '');
        $name = trim((string) ($request->json('name') ?? ''));

        if ($email === '' || $password === '' || $name === '') {
            return $this->error('Email, password, and name are required', 400);
        }

        // None of these were validated before: any string was accepted as
        // an email, and any password — including a one-character one —
        // was hashed and stored.
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->error('Please provide a valid email address', 400);
        }

        if (strlen($password) < 8) {
            return $this->error('Password must be at least 8 characters', 400);
        }

        try {
            $existing = QueryBuilder::table('users')->where('email', $email)->first();

            if ($existing !== null) {
                return $this->error('Email already in use', 400);
            }

            $parts = preg_split('/\s+/u', $name, 2) ?: [$name];

            $id = QueryBuilder::table('users')->insertGetId([
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_ARGON2ID),
                'first_name' => mb_substr($parts[0] ?? '', 0, 100),
                'last_name' => mb_substr($parts[1] ?? '', 0, 100),
                'role' => 'customer',
                'is_active' => 1,
            ]);

            return $this->success(['id' => $id], 'Registration successful', 201);
        } catch (\Throwable $e) {
            Logger::exception($e);

            return $this->error('Registration failed. Please try again.', 500);
        }
    }
}
