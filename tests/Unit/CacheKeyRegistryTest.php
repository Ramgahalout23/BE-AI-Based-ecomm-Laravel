<?php

namespace Tests\Unit;

use App\Traits\CacheKeyRegistry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Tests\TestCase;

class CacheKeyRegistryTest extends TestCase
{
    /**
     * Build a minimal object exposing the trait's protected cache methods.
     */
    private function service(): object
    {
        return new class {
            use CacheKeyRegistry;

            public function cached(string $key, int $ttl, callable $callback): mixed
            {
                return $this->cacheWithTracking($key, $ttl, $callback);
            }
        };
    }

    public function test_caches_and_returns_plain_array(): void
    {
        $result = $this->service()->cached('guard_test_array', 300, fn () => ['a' => 1]);

        $this->assertSame(['a' => 1], $result);
        $this->assertSame(['a' => 1], Cache::get('guard_test_array'));
    }

    public function test_caches_and_returns_scalar_and_null(): void
    {
        $service = $this->service();

        $this->assertSame('value', $service->cached('guard_test_scalar', 300, fn () => 'value'));
        $this->assertNull($service->cached('guard_test_null', 300, fn () => null));
    }

    public function test_allows_json_serializable_objects(): void
    {
        $collection = collect([['id' => 1], ['id' => 2]]);

        $result = $this->service()->cached('guard_test_collection', 300, fn () => $collection);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertInstanceOf(Collection::class, Cache::get('guard_test_collection'));
    }

    public function test_rejects_response_objects(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Response');

        $this->service()->cached('guard_test_response', 300, fn () => response()->json(['success' => true]));
    }

    public function test_rejects_std_class_from_get_data(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service()->cached('guard_test_stdclass', 300, fn () => response()->json(['data' => [1]])->getData());
    }

    public function test_rejects_unsupported_objects(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service()->cached('guard_test_datetime', 300, fn () => new \DateTime());
    }

    public function test_cache_hits_bypass_the_guard(): void
    {
        // Pre-seed a stale entry (e.g. the old stdClass written before the
        // guard existed in ReelController::adminIndex) — hits return it as-is,
        // so stale entries expire naturally instead of crashing every request.
        Cache::put('guard_test_stale', (object) ['success' => true, 'data' => []]);

        $result = $this->service()->cached('guard_test_stale', 300, fn () => ['fresh' => true]);

        $this->assertEquals((object) ['success' => true, 'data' => []], $result);
    }
}
