<?php

namespace App\Traits;

use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cache key registry trait.
 *
 * Tracks all cache keys created via cacheWithTracking() in a registry stored
 * under '_cache_keys_registry'. When clearTrackedCache() is called, every
 * registered key is forgotten — including ones with dynamic parameters that
 * a hardcoded static list would miss.
 *
 * Usage:
 *   class YourService
 *   {
 *       use CacheKeyRegistry;
 *
 *       public function getFoo(string $id): array
 *       {
 *           return $this->cacheWithTracking("foo_{$id}", 300, function () use ($id) {
 *               return // ... expensive computation
 *           });
 *       }
 *
 *       public function invalidateAll(): void
 *       {
 *           $this->clearTrackedCache();
 *       }
 *   }
 */
trait CacheKeyRegistry
{
    /**
     * Cache a value and track the key in the registry so clearTrackedCache()
     * can clear it even when keys have dynamic parameters.
     *
     * The callback result is validated by assertCacheableValue() before it is
     * written to the cache store — response objects and stdClass payloads are
     * rejected (see regression guard below).
     */
    protected function cacheWithTracking(string $key, int $ttl, callable $callback): mixed
    {
        $this->trackCacheKey($key);

        return Cache::remember($key, $ttl, function () use ($callback, $key) {
            $value = $callback();
            $this->assertCacheableValue($value, $key);

            return $value;
        });
    }

    /**
     * Regression guard for cacheWithTracking():
     *
     * Closures must return serializable payloads - never Response objects or
     * stdClass (the shape produced by response()->json(...)->getData(), which
     * caused a TypeError/500 in ReelController::adminIndex()).
     *
     * Allowed: null, scalars, arrays, and JsonSerializable objects
     * (Eloquent models/collections are cached legitimately across the app).
     */
    protected function assertCacheableValue(mixed $value, string $key): void
    {
        if (is_null($value) || is_scalar($value) || is_array($value)) {
            return;
        }

        if ($value instanceof Response) {
            throw new InvalidArgumentException(
                "cacheWithTracking('{$key}'): closure returned a " . get_class($value) . ". "
                . 'Return a plain array/primitive instead of a response object - build the response AFTER the cache call.'
            );
        }

        if ($value instanceof \stdClass) {
            throw new InvalidArgumentException(
                "cacheWithTracking('{$key}'): closure returned a stdClass (likely from response()->json(...)->getData()). "
                . 'Return a plain array instead - the cache stores data, not response payloads.'
            );
        }

        if ($value instanceof \JsonSerializable) {
            return;
        }

        throw new InvalidArgumentException(
            "cacheWithTracking('{$key}'): closure returned an unsupported value of type "
            . get_debug_type($value) . '. Return a serializable array, scalar, or JsonSerializable object.'
        );
    }

    /**
     * Register a cache key so it gets cleared on the next clearTrackedCache() call.
     */
    protected function trackCacheKey(string $key): void
    {
        $registryKey = $this->registryKey();
        $keys = Cache::get($registryKey, []);
        if (!in_array($key, $keys, true)) {
            $keys[] = $key;
            Cache::forever($registryKey, $keys);
        }
    }

    /**
     * Clear all tracked cache keys.
     * Call this from your mutation methods (create/update/delete).
     */
    protected function clearTrackedCache(): void
    {
        $registryKey = $this->registryKey();
        $keys = Cache::get($registryKey, []);
        foreach ($keys as $key) {
            Cache::forget($key);
        }
        Cache::forget($registryKey);
    }

    /**
     * Get the registry key for this class.
     * Override in individual classes to use a unique registry per service.
     */
    protected function registryKey(): string
    {
        return '_cache_registry_' . str_replace('\\', '_', static::class);
    }
}
