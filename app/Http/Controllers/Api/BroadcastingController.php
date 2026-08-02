<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RealtimeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class BroadcastingController extends Controller
{
    public function __construct(
        protected RealtimeService $realtimeService
    ) {
    }

    /**
     * Return Pusher config for the frontend (public-safe data only).
     */
    public function config(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'key' => config('services.pusher.key') ?? env('PUSHER_APP_KEY'),
                'cluster' => config('services.pusher.options.cluster') ?? env('PUSHER_APP_CLUSTER', 'mt1'),
            ],
        ]);
    }

    /**
     * Authorize a Pusher private-channel subscription.
     *
     * Mirrors the channel rules used by the realtime driver:
     *   - private-user.{id} → only the matching authenticated customer
     *   - private-admin      → only ADMIN / SUPER_ADMIN users
     *
     * Returns the standard `{auth: "key:signature"}` payload expected by pusher-js.
     */
    public function authenticate(Request $request): JsonResponse
    {
        $channel = $request->input('channel_name');
        $socketId = $request->input('socket_id');

        if (!$channel || !$socketId) {
            throw new AccessDeniedHttpException('Missing channel_name or socket_id');
        }

        $user = $request->user();
        if (!$user) {
            throw new AccessDeniedHttpException('Unauthenticated');
        }

        if (str_starts_with($channel, 'private-user.')) {
            $ownerId = substr($channel, strlen('private-user.'));
            if ((string) $user->id !== (string) $ownerId) {
                throw new AccessDeniedHttpException('Channel belongs to another user');
            }
        } elseif ($channel === 'private-admin') {
            if (!$user->isAdmin()) {
                throw new AccessDeniedHttpException('Admins only');
            }
        } else {
            throw new AccessDeniedHttpException('Unknown channel');
        }

        $auth = $this->realtimeService->authorizeChannel($channel, $socketId);
        if ($auth === null) {
            // Pusher isn't configured — a misconfiguration, not an auth failure.
            return response()->json(['error' => 'Realtime channel auth unavailable'], 503);
        }

        return response()->json(['auth' => $auth]);
    }
}
