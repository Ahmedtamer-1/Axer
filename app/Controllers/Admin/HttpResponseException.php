<?php

namespace Axer\Controllers\Admin;

use Axer\Core\Response;

/**
 * Control-flow exception carrying a ready-made Response.
 *
 * Guards such as checkAuth() need to abort the current action from deep
 * inside a controller. They used to do that with `header(); exit;`, which
 * skipped the response pipeline and the security headers with it. Throwing
 * this instead lets the Router unwind normally and send a proper Response.
 */
class HttpResponseException extends \RuntimeException
{
    public function __construct(private readonly Response $response)
    {
        parent::__construct('Request aborted by a controller guard.');
    }

    public function getResponse(): Response
    {
        return $this->response;
    }
}
