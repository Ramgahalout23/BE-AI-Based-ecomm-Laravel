<?php

namespace App\Repositories;

use App\Models\PageView;
use App\Models\UserSession;
use App\Models\UserEvent;
use Illuminate\Database\Eloquent\Collection;

class TrackingRepository
{
    public function recordPageView(string $url, ?string $userId, ?string $sessionId, ?string $referrer, ?string $title = null, ?string $userAgent = null, ?string $device = null): PageView
    {
        return PageView::create([
            'user_id' => $userId,
            'session_id' => $sessionId,
            'url' => $url,
            'referrer' => $referrer,
            'title' => $title,
            'user_agent' => $userAgent,
            'device' => $device,
        ]);
    }

    public function createSession(?string $sessionId, ?string $userId, string $ip, string $userAgent, ?string $device = null, ?string $browser = null, ?string $os = null, ?string $referrer = null, ?string $landingPage = null): UserSession
    {
        // Use updateOrCreate to handle page refreshes gracefully:
        // if a session with this session_id already exists (e.g. from a previous
        // page load), update it instead of throwing a duplicate-key 500 error.
        return UserSession::updateOrCreate(
            ['session_id' => $sessionId],
            [
                'user_id' => $userId,
                'ip_address' => $ip,
                'user_agent' => $userAgent,
                'device' => $device,
                'browser' => $browser,
                'os' => $os,
                'referrer' => $referrer,
                'landing_page' => $landingPage,
                'start_time' => now(),
                'is_active' => true,
            ]
        );
    }

    public function endSession(string $sessionId): void
    {
        UserSession::where('session_id', $sessionId)->update(['end_time' => now(), 'is_active' => false]);
    }

    public function recordEvent(string $sessionId, string $eventType, ?string $eventName = null, ?string $category = null, ?string $label = null, ?string $value = null, ?string $url = null, ?string $metadata = null, ?string $userId = null): UserEvent
    {
        return UserEvent::create([
            'session_id' => $sessionId,
            'user_id' => $userId,
            'event_type' => $eventType,
            'event_name' => $eventName,
            'category' => $category,
            'label' => $label,
            'value' => $value,
            'url' => $url,
            'metadata' => $metadata ? (is_string($metadata) ? $metadata : json_encode($metadata)) : null,
        ]);
    }

    public function getPageViewsStats(): array
    {
        return [
            'total' => PageView::count(),
            'today' => PageView::whereDate('created_at', today())->count(),
            'unique_urls' => PageView::distinct('url')->count('url'),
        ];
    }

    public function getActiveSessions(): int
    {
        return UserSession::where('is_active', true)
            ->where('start_time', '>=', now()->subHours(2))
            ->count();
    }

    public function getTopPages(int $limit = 10): Collection
    {
        return PageView::select('url')
            ->selectRaw('COUNT(*) as views')
            ->groupBy('url')
            ->orderByDesc('views')
            ->take($limit)
            ->get();
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
