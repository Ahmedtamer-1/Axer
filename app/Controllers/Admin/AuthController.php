<?php

namespace Axer\Controllers\Admin;

use Axer\Core\Csrf;
use Axer\Core\Logger;
use Axer\Core\RateLimiter;
use Axer\Core\Request;
use Axer\Core\Response;
use Axer\Core\Session;
use Axer\Database\QueryBuilder;

class AuthController extends AdminController
{
    /** Failed sign-ins allowed per IP before the form locks out. */
    private const MAX_ATTEMPTS = 8;
    private const LOCKOUT_SECONDS = 900;

    public function showLogin(Request $request): Response
    {
        if (isset($_SESSION['admin_user'])) {
            return $this->redirect('/admin/dashboard');
        }

        return $this->renderAdmin('auth/login', [], false);
    }

    public function login(Request $request): Response
    {
        if (isset($_SESSION['admin_user'])) {
            return $this->redirect('/admin/dashboard');
        }

        $limiter = new RateLimiter();
        $key = 'admin-login:' . RateLimiter::clientIp();

        // There was no throttling at all: the login form accepted unlimited
        // password guesses.
        if ($limiter->attempts($key, self::LOCKOUT_SECONDS) >= self::MAX_ATTEMPTS) {
            $minutes = (int) ceil($limiter->availableIn($key, self::LOCKOUT_SECONDS) / 60);

            return $this->renderAdmin('auth/login', [
                'error' => "Too many failed attempts. Please try again in {$minutes} minute(s).",
            ], false);
        }

        $email = strtolower($request->string('email'));
        $password = (string) $request->post('password', '');

        if ($email === '' || $password === '') {
            return $this->renderAdmin('auth/login', [
                'error' => 'Please enter both email and password.',
                'email' => $email,
            ], false);
        }

        try {
            $user = QueryBuilder::table('users')
                ->where('email', $email)
                ->whereIn('role', ['admin', 'superadmin'])
                ->where('is_active', 1)
                ->first();
        } catch (\Throwable $e) {
            Logger::exception($e);

            return $this->renderAdmin('auth/login', [
                'error' => 'We could not reach the database. Please try again.',
                'email' => $email,
            ], false);
        }

        // Always run a verification, even when the account does not exist,
        // so response time does not reveal which emails are registered.
        $hash = $user['password_hash'] ?? '$2y$12$usesomesillystringfoeu1f7.4mnPYsl8oCVGvJ3.9ycCCbfW9pi';

        if ($user === null || !password_verify($password, $hash)) {
            $limiter->hit($key, self::LOCKOUT_SECONDS);

            Logger::warning('Failed admin login', ['email' => $email, 'ip' => RateLimiter::clientIp()]);

            return $this->renderAdmin('auth/login', [
                'error' => 'Invalid email or password.',
                'email' => $email,
            ], false);
        }

        // Issue a brand-new session ID now that the visitor is privileged.
        // Without this, an attacker who fixed the victim's session ID before
        // login would still hold a valid admin session afterwards.
        Session::regenerate();
        Csrf::rotate();
        $limiter->clear($key);

        $_SESSION['admin_user'] = [
            'id' => $user['id'],
            'email' => $user['email'],
            'role' => $user['role'],
            'name' => trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: 'Admin',
        ];

        // Rehash if the cost factor has moved on since the account was made.
        if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            try {
                QueryBuilder::table('users')->where('id', $user['id'])->update([
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                ]);
            } catch (\Throwable $e) {
                Logger::warning('Could not rehash password: ' . $e->getMessage());
            }
        }

        try {
            QueryBuilder::table('users')->where('id', $user['id'])->update([
                'last_login_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            // last_login_at is optional metadata.
        }

        $intended = Session::get('intended_url');
        Session::forget('intended_url');

        // Only follow a same-site path, never an absolute URL.
        $target = is_string($intended) && str_starts_with($intended, '/admin/')
            ? $intended
            : '/admin/dashboard';

        return $this->redirect($target);
    }

    public function logout(Request $request): Response
    {
        // session_destroy() alone left the cookie in the browser pointing at
        // a dead session; Session::destroy() clears the cookie too.
        Session::destroy();

        return $this->redirect('/admin/login');
    }
}
