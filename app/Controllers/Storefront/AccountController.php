<?php

namespace Axer\Controllers\Storefront;

use Axer\Core\Controller;
use Axer\Core\Csrf;
use Axer\Core\RateLimiter;
use Axer\Core\Request;
use Axer\Core\Response;
use Axer\Core\Session;
use Axer\Services\AccountService;
use Axer\Services\ThemeService;

/**
 * Session-based customer accounts.
 *
 * The storefront had no login at all before this — Api\AuthController
 * implemented registration and login but nothing routed to it. This gives
 * a visitor a normal cookie session (mirroring how Admin\AuthController
 * signs in staff) rather than the bearer-token flow the JSON API uses.
 */
class AccountController extends Controller
{
    private const MAX_LOGIN_ATTEMPTS = 8;
    private const LOCKOUT_SECONDS = 900;

    protected function render(string $view, array $data = [], int $status = 200): Response
    {
        return new Response(ThemeService::renderPage($view, $data), $status);
    }

    public function showLogin(Request $request): Response
    {
        if (isset($_SESSION['user'])) {
            return $this->redirect('/account');
        }

        return $this->render('account/login', ['page_title' => 'Sign In']);
    }

    public function login(Request $request): Response
    {
        if (isset($_SESSION['user'])) {
            return $this->redirect('/account');
        }

        $limiter = new RateLimiter();
        $key = 'account-login:' . RateLimiter::clientIp();

        if ($limiter->attempts($key, self::LOCKOUT_SECONDS) >= self::MAX_LOGIN_ATTEMPTS) {
            $minutes = (int) ceil($limiter->availableIn($key, self::LOCKOUT_SECONDS) / 60);

            return $this->render('account/login', [
                'page_title' => 'Sign In',
                'error' => "Too many failed attempts. Please try again in {$minutes} minute(s).",
            ], 429);
        }

        $email = $request->string('email');
        $password = (string) $request->post('password', '');

        if ($email === '' || $password === '') {
            return $this->render('account/login', [
                'page_title' => 'Sign In',
                'error' => 'Please enter both email and password.',
                'email' => $email,
            ], 400);
        }

        $user = AccountService::attempt($email, $password, 'customer');

        if ($user === null) {
            $limiter->hit($key, self::LOCKOUT_SECONDS);

            return $this->render('account/login', [
                'page_title' => 'Sign In',
                'error' => 'Invalid email or password.',
                'email' => $email,
            ], 401);
        }

        $limiter->clear($key);

        // Same session-fixation defence as the admin login: a fresh
        // session ID and CSRF token now that this session is privileged.
        Session::regenerate();
        Csrf::rotate();

        $_SESSION['user'] = [
            'id' => $user['id'],
            'email' => $user['email'],
            'name' => trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: 'Customer',
        ];

        $intended = Session::get('intended_account_url');
        Session::forget('intended_account_url');

        $target = is_string($intended) && str_starts_with($intended, '/account')
            ? $intended
            : '/account';

        return $this->redirect($target);
    }

    public function showRegister(Request $request): Response
    {
        if (isset($_SESSION['user'])) {
            return $this->redirect('/account');
        }

        return $this->render('account/register', ['page_title' => 'Create Account']);
    }

    public function register(Request $request): Response
    {
        if (isset($_SESSION['user'])) {
            return $this->redirect('/account');
        }

        $email = $request->string('email');
        $password = (string) $request->post('password', '');
        $name = trim((string) $request->post('name', ''));

        $result = AccountService::register($email, $password, $name);

        if (!$result['ok']) {
            return $this->render('account/register', [
                'page_title' => 'Create Account',
                'error' => $result['message'],
                'email' => $email,
                'name' => $name,
            ], 400);
        }

        Session::flash('success', 'Account created. Please sign in.');

        return $this->redirect('/account/login');
    }

    public function logout(Request $request): Response
    {
        unset($_SESSION['user']);
        Session::regenerate();

        return $this->redirect('/');
    }

    public function dashboard(Request $request): Response
    {
        if (!isset($_SESSION['user'])) {
            Session::put('intended_account_url', $request->uri());

            return $this->redirect('/account/login');
        }

        return $this->render('account/index', [
            'page_title' => 'My Account',
            'account' => $_SESSION['user'],
        ]);
    }
}
