<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $levels = [];
    protected $dontReport = [];
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    public function register()
    {
        $this->reportable(function (Throwable $e) {});
    }

    public function render($request, Throwable $e)
    {
        if ($e instanceof AppError) {
            if ($request->expectsJson()) {
                return $e->render();
            }
            // For web requests, use appropriate HTTP error pages
            abort($e->getStatusCode(), $e->getMessage());
        }
        return parent::render($request, $e);
    }

    /**
     * Convert an authentication exception into a response.
     *
     * This overrides the default behaviour which tries to redirect to a named
     * "login" route (which doesn't exist in this API-only app) and instead
     * returns a consistent JSON 401 response for all unauthenticated requests.
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        return response()->json([
            'success' => false,
            'message' => 'Unauthenticated. Please provide a valid Bearer token.',
        ], 401);
    }
}
