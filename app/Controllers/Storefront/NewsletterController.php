<?php

namespace Axer\Controllers\Storefront;

use Axer\Core\Controller;
use Axer\Core\Logger;
use Axer\Core\Request;
use Axer\Core\Response;
use Axer\Core\Session;
use Axer\Database\QueryBuilder;

/**
 * The `subscribers` table has existed since migration 017 with no route,
 * controller, or form action ever wired to it — the newsletter block on
 * every theme page rendered a "Subscribe" button with no `action` or
 * `method` on its form, so submitting it did nothing at all.
 */
class NewsletterController extends Controller
{
    public function subscribe(Request $request): Response
    {
        $email = strtolower(trim((string) $request->post('email', '')));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->respond($request, false, 'Please enter a valid email address.', 422);
        }

        try {
            $existing = QueryBuilder::table('subscribers')->where('email', $email)->first();

            if ($existing !== null) {
                if ($existing['status'] !== 'active') {
                    QueryBuilder::table('subscribers')->where('id', $existing['id'])->update([
                        'status' => 'active',
                        'subscribed_at' => date('Y-m-d H:i:s'),
                    ]);
                }
            } else {
                QueryBuilder::table('subscribers')->insert([
                    'email' => $email,
                    'source' => 'website',
                ]);
            }
        } catch (\Throwable $e) {
            Logger::exception($e);

            return $this->respond($request, false, 'Could not subscribe right now. Please try again.', 500);
        }

        return $this->respond($request, true, 'Thanks for subscribing!');
    }

    protected function respond(Request $request, bool $ok, string $message, int $status = 200): Response
    {
        if ($request->expectsJson()) {
            return Response::json(['success' => $ok, 'message' => $message], $status);
        }

        if (!$ok) {
            Session::flash('error', $message);
        } else {
            Session::flash('success', $message);
        }

        $referer = $request->header('Referer');

        return $this->redirect(is_string($referer) && $referer !== '' ? $referer : '/');
    }
}
