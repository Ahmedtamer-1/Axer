<?php

namespace Axer\Controllers\Admin;

use Axer\Core\Request;
use Axer\Core\Response;
use Axer\Database\QueryBuilder;

class AuthController extends AdminController
{
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

        $email = trim($request->post('email') ?? '');
        $password = $request->post('password') ?? '';
        $error = null;

        if (empty($email) || empty($password)) {
            $error = 'Please enter both email and password.';
        } else {
            try {
                $user = QueryBuilder::table('users')
                    ->where('email', $email)
                    ->whereIn('role', ['admin', 'superadmin'])
                    ->where('is_active', 1)
                    ->first();

                if ($user && password_verify($password, $user['password_hash'])) {
                    $_SESSION['admin_user'] = [
                        'id' => $user['id'],
                        'email' => $user['email'],
                        'role' => $user['role'],
                        'name' => trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: 'Admin'
                    ];
                    return $this->redirect('/admin/dashboard');
                } else {
                    $error = 'Invalid email or password.';
                }
            } catch (\Exception $e) {
                $error = 'Database connection error. Please try again.';
            }
        }

        return $this->renderAdmin('auth/login', ['error' => $error], false);
    }

    public function logout(Request $request): Response
    {
        unset($_SESSION['admin_user']);
        session_destroy();
        return $this->redirect('/admin/login');
    }
}
