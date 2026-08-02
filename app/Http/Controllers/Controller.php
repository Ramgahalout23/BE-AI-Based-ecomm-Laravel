<?php

namespace App\Http\Controllers;

use App\Exceptions\AppError;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    /**
     * Render an unexpected exception without leaking internals to clients.
     *
     * - AppError: rendered with its own structured status + body.
     * - ValidationException / HttpExceptionInterface: re-thrown so Laravel's
     *   exception handler returns the proper response (422, 401, 403, 404...).
     * - Anything else: logged server-side and answered with a generic message
     *   while preserving the given HTTP status.
     */
    protected function handleUnexpectedException(\Exception $e, int $status = 500): JsonResponse
    {
        if ($e instanceof AppError) {
            return $e->render();
        }

        if ($e instanceof ValidationException || $e instanceof HttpExceptionInterface) {
            throw $e;
        }

        Log::error('[' . class_basename(static::class) . '] ' . $e->getMessage());

        return response()->json([
            'success' => false,
            'message' => $status === 404 ? 'Resource not found.' : 'Something went wrong. Please try again later.',
        ], $status);
    }
}
