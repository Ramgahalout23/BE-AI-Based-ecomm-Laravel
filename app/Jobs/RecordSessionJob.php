<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RecordSessionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected ?string $sessionId,
        protected ?string $userId,
        protected string $ip,
        protected string $userAgent,
        protected ?string $device,
        protected ?string $browser,
        protected ?string $os,
        protected ?string $referrer,
        protected ?string $landingPage
    ) {}

    /**
     * Execute the job — upsert session using raw DB for maximum speed.
     * Uses updateOrInsert (upsert) to handle page refreshes gracefully.
     */
    public function handle(): void
    {
        try {
            $now = now();
            $exists = DB::table('user_sessions')->where('session_id', $this->sessionId)->exists();

            if ($exists) {
                DB::table('user_sessions')
                    ->where('session_id', $this->sessionId)
                    ->update([
                        'user_id' => $this->userId,
                        'ip_address' => $this->ip,
                        'user_agent' => $this->userAgent,
                        'device' => $this->device,
                        'browser' => $this->browser,
                        'os' => $this->os,
                        'referrer' => $this->referrer,
                        'landing_page' => $this->landingPage,
                        'start_time' => $now,
                        'is_active' => true,
                        'updated_at' => $now,
                    ]);
            } else {
                DB::table('user_sessions')->insert([
                    'id' => (string) Str::orderedUuid(),
                    'session_id' => $this->sessionId,
                    'user_id' => $this->userId,
                    'ip_address' => $this->ip,
                    'user_agent' => $this->userAgent,
                    'device' => $this->device,
                    'browser' => $this->browser,
                    'os' => $this->os,
                    'referrer' => $this->referrer,
                    'landing_page' => $this->landingPage,
                    'start_time' => $now,
                    'is_active' => true,
                    'duration' => 0,
                    'page_views' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('[RecordSessionJob] Failed to record session', [
                'session_id' => $this->sessionId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("[RecordSessionJob] Permanently failed for session {$this->sessionId}: {$exception->getMessage()}");
    }
}
