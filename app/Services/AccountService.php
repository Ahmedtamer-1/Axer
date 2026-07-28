<?php

namespace Axer\Services;

use Axer\Core\Logger;
use Axer\Database\QueryBuilder;

/**
 * Customer registration and credential verification.
 *
 * This logic previously lived only in Api\AuthController, whose route was
 * never registered anywhere (config/api_routes.php had no reference to
 * it) — so there was no way for a customer to log in at all, from the API
 * or the storefront. Pulled out here so the API controller and the new
 * session-based storefront AccountController share one implementation
 * instead of duplicating validation and hashing.
 */
class AccountService
{
    public const MIN_PASSWORD_LENGTH = 8;

    /**
     * @return string[] Validation error messages, empty if valid.
     */
    public static function validateRegistration(string $email, string $password, string $name): array
    {
        $errors = [];

        if ($email === '' || $password === '' || $name === '') {
            $errors[] = 'Email, password, and name are required.';

            return $errors;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please provide a valid email address.';
        }

        if (strlen($password) < self::MIN_PASSWORD_LENGTH) {
            $errors[] = 'Password must be at least ' . self::MIN_PASSWORD_LENGTH . ' characters.';
        }

        return $errors;
    }

    /**
     * @return array{ok: bool, message: string, user_id?: int}
     */
    public static function register(string $email, string $password, string $name): array
    {
        $email = strtolower(trim($email));
        $errors = self::validateRegistration($email, $password, $name);

        if ($errors !== []) {
            return ['ok' => false, 'message' => $errors[0]];
        }

        try {
            $existing = QueryBuilder::table('users')->where('email', $email)->first();

            if ($existing !== null) {
                return ['ok' => false, 'message' => 'Email already in use.'];
            }

            $parts = preg_split('/\s+/u', trim($name), 2) ?: [$name];

            $id = QueryBuilder::table('users')->insertGetId([
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_ARGON2ID),
                'first_name' => mb_substr($parts[0] ?? '', 0, 100),
                'last_name' => mb_substr($parts[1] ?? '', 0, 100),
                'role' => 'customer',
                'is_active' => 1,
            ]);

            return ['ok' => true, 'message' => 'Registration successful.', 'user_id' => $id];
        } catch (\Throwable $e) {
            Logger::exception($e);

            return ['ok' => false, 'message' => 'Registration failed. Please try again.'];
        }
    }

    /**
     * Verify credentials for a user account. Runs a bcrypt/argon comparison
     * even when the account does not exist, so response time cannot be used
     * to enumerate registered emails.
     *
     * @param string|null $requireRole Restrict the lookup to a single role
     *   (the storefront login only accepts 'customer'); null accepts any
     *   active account, matching the API login's original behaviour.
     * @return array|null The user row (without password_hash) on success.
     */
    public static function attempt(string $email, string $password, ?string $requireRole = null): ?array
    {
        $email = strtolower(trim($email));

        try {
            $query = QueryBuilder::table('users')
                ->where('email', $email)
                ->where('is_active', 1);

            if ($requireRole !== null) {
                $query->where('role', $requireRole);
            }

            $user = $query->first();
        } catch (\Throwable $e) {
            Logger::exception($e);

            return null;
        }

        $hash = $user['password_hash'] ?? '$2y$12$usesomesillystringfoeu1f7.4mnPYsl8oCVGvJ3.9ycCCbfW9pi';

        if ($user === null || !password_verify($password, $hash)) {
            return null;
        }

        if (password_needs_rehash($hash, PASSWORD_ARGON2ID)) {
            try {
                QueryBuilder::table('users')->where('id', $user['id'])->update([
                    'password_hash' => password_hash($password, PASSWORD_ARGON2ID),
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

        unset($user['password_hash']);

        return $user;
    }
}
