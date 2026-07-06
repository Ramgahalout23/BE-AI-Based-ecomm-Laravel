<?php

namespace App\Repositories;

use App\Models\PageView;
use App\Models\UserSession;
use App\Models\UserEvent;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class TrackingRepository
{
    public function endSession(string $sessionId): void
    {
        UserSession::where('session_id', $sessionId)->update(['end_time' => now(), 'is_active' => false]);
    }

    public function getPageViewsStats(): array
    {
        return Cache::remember('tracking_pageview_stats', 300, function () {
            return [
                'total' => PageView::count(),
                'today' => PageView::whereDate('created_at', today())->count(),
                'unique_urls' => PageView::distinct('url')->count('url'),
            ];
        });
    }

    public function getActiveSessions(): int
    {
        return Cache::remember('tracking_active_sessions', 60, function () {
            return UserSession::where('is_active', true)
                ->where('start_time', '>=', now()->subHours(2))
                ->count();
        });
    }

    public function getTopPages(int $limit = 10): Collection
    {
        return Cache::remember('tracking_top_pages', 300, function () use ($limit) {
            return PageView::select('url')
                ->selectRaw('COUNT(*) as views')
                ->groupBy('url')
                ->orderByDesc('views')
                ->take($limit)
                ->get();
        });
    }

    /**
     * Get page views by user for journey.
     */
    public function getPageViewsByUser(string $userId): Collection
    {
        return PageView::where('user_id', $userId)
            ->orderBy('created_at', 'asc')
            ->get();
    }

    /**
     * Get events by user through sessions.
     */
    public function getEventsByUser(string $userId): Collection
    {
        return UserEvent::where('user_id', $userId)
            ->with('session')
            ->latest()
            ->get();
    }

    /**
     * Get sessions by user.
     */
    public function getSessionsByUser(string $userId): Collection
    {
        return UserSession::where('user_id', $userId)
            ->orderBy('start_time', 'desc')
            ->get();
    }
}
