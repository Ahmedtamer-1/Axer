<?php

namespace Axer\Controllers\Admin;

use Axer\Core\Request;
use Axer\Core\Response;
use Axer\Database\QueryBuilder;

class AuthController extends AdminController
{
    public function login(Request $request): Response
    {
        if (isset($_SESSION['admin_user'])) {
            return $this->redirect('/admin/dashboard');
        }

        $error = null;

        if ($request->method() === 'POST') {
            $email = $request->post('email');
            $password = $request->post('password');

            try {
                $user = QueryBuilder::table('users')
                    ->where('email', $email)
                    ->where('role', 'admin')
                    ->where('is_active', 1)
                    ->first();

                if ($user && password_verify($password, $user['password_hash'])) {
                    $_SESSION['admin_user'] = [
                        'id' => $user['id'],
                        'email' => $user['email'],
                        'name' => trim($user['first_name'] . ' ' . $user['last_name'])
                    ];
                    return $this->redirect('/admin/dashboard');
                } else {
                    $error = 'Invalid email or password.';
                }
            } catch (\Exception $e) {
                $error = 'Database error. Please try again.';
            }
        }

        // Render standalone login view
        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Lume Admin Login</title>
            <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
            <style>
                body { font-family: 'Outfit', sans-serif; background: #0f172a; color: #f8fafc; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
                .login-card { background: #1e293b; padding: 2.5rem; border-radius: 0.5rem; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.5); width: 100%; max-width: 400px; }
                h1 { margin-top: 0; font-size: 1.75rem; color: #6366f1; text-align: center; margin-bottom: 2rem; }
                .form-group { margin-bottom: 1.25rem; }
                label { display: block; margin-bottom: 0.5rem; font-size: 0.875rem; font-weight: 500; }
                input[type="email"], input[type="password"] { width: 100%; padding: 0.75rem; border-radius: 0.375rem; border: 1px solid #334155; background: #0f172a; color: white; box-sizing: border-box; }
                input:focus { outline: none; border-color: #6366f1; box-shadow: 0 0 0 2px rgba(99,102,241,0.2); }
                .btn { display: inline-block; width: 100%; padding: 0.875rem; background: #6366f1; color: white; border: none; border-radius: 0.375rem; font-weight: 600; cursor: pointer; text-align: center; text-decoration: none; box-sizing: border-box; margin-top: 1rem; }
                .btn:hover { background: #4f46e5; }
                .alert-error { background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); color: #fca5a5; padding: 1rem; border-radius: 0.375rem; margin-bottom: 1.5rem; font-size: 0.875rem; }
            </style>
        </head>
        <body>
            <div class="login-card">
                <h1>Lume Login</h1>
                <?php if ($error): ?>
                    <div class="alert-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <form method="POST">
    <input type="hidden" name="_csrf" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" required>
                    </div>
                    <button type="submit" class="btn">Sign In</button>
                </form>
            </div>
        </body>
        </html>
        <?php
        $html = ob_get_clean();
        return new Response($html);
    }

    public function logout(Request $request): Response
    {
        unset($_SESSION['admin_user']);
        return $this->redirect('/admin/login');
    }
}
