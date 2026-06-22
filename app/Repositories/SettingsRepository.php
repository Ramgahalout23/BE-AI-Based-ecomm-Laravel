<?php

namespace App\Repositories;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class SettingsRepository extends BaseRepository
{
    protected function modelClass(): string
    {
        return Setting::class;
    }

    public function getValue(string $key, mixed $default = null): mixed
    {
        $cacheKey = "setting_{$key}";
        return Cache::remember($cacheKey, 600, function () use ($key, $default) {
            $setting = Setting::where('key', $key)->first();
            return $setting ? $setting->value : $default;
        });
    }

    public function setValue(string $key, mixed $value): Setting
    {
        Cache::forget('settings_all');
        Cache::forget("setting_{$key}");
        return Setting::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    public function getAllAsArray(): array
    {
        return Cache::remember('settings_all', 600, function () {
            return Setting::pluck('value', 'key')->toArray();
        });
    }
}
