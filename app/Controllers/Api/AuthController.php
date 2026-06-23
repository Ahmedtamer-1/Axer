<?php

namespace Lume\Controllers\Api;

use Lume\Core\Request;
use Lume\Core\Response;
use Lume\Database\QueryBuilder;

class AuthController extends ApiController
{
    public function login(Request $request): Response
    {
        $email = $request->json('email');
        $password = $request->json('password');

        if (!$email || !$password) {
            return $this->error('Email and password required', 400);
        }

        $user = QueryBuilder::table('users')
            ->where('email', $email)
            ->where('is_active', 1)
            ->first();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return $this->error('Invalid credentials', 401);
        }

        // Enforce Argon2id upgrade
        if (password_needs_rehash($user['password_hash'], PASSWORD_ARGON2ID)) {
            $newHash = password_hash($password, PASSWORD_ARGON2ID);
            QueryBuilder::table('users')->where('id', $user['id'])->update([
                'password_hash' => $newHash
            ]);
        }

        // Generate token
        $tokenStr = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $tokenStr);

        QueryBuilder::table('api_tokens')->insert([
            'user_id' => $user['id'],
            'token_hash' => $tokenHash,
            'name' => 'API Login',
            'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days'))
        ]);
        
        // Update last login
        QueryBuilder::table('users')->where('id', $user['id'])->update([
            'last_login_at' => date('Y-m-d H:i:s')
        ]);

        unset($user['password_hash']);

        return $this->success([
            'token' => $tokenStr,
            'user' => $user
        ], 'Login successful');
    }

    public function register(Request $request): Response
    {
        $email = $request->json('email');
        $password = $request->json('password');
        $name = $request->json('name');

        if (!$email || !$password || !$name) {
            return $this->error('Email, password, and name required', 400);
        }

        $existing = QueryBuilder::table('users')->where('email', $email)->first();
        if ($existing) {
            return $this->error('Email already in use', 400);
        }

        $hash = password_hash($password, PASSWORD_ARGON2ID);
        
        try {
            $id = QueryBuilder::table('users')->insert([
                'email' => $email,
                'password_hash' => $hash,
                'first_name' => $name,
                'is_active' => 1
            ]);
            
            return $this->success(['id' => $id], 'Registration successful', 201);
        } catch (\Exception $e) {
            return $this->error('Registration failed', 500);
        }
    }
}
