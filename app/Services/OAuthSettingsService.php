<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Log;

class OAuthSettingsService
{
    /**
     * Resolve Google OAuth credentials.
     * Priority: env vars > DB settings.
     */
    public function getGoogleCredentials(): array
    {
        $db = $this->getCachedOAuthSettings();

        return [
            'client_id' => config('services.google.client_id') ?: ($db['googleClientId'] ?? null),
            'client_secret' => config('services.google.client_secret') ?: ($db['googleClientSecret'] ?? null),
            'callback_url' => $this->resolveCallbackUrl('google', '/api/v1/auth/google/callback'),
        ];
    }

    /**
     * Resolve Facebook OAuth credentials.
     * Priority: env vars > DB settings.
     */
    public function getFacebookCredentials(): array
    {
        $db = $this->getCachedOAuthSettings();

        return [
            'client_id' => config('services.facebook.client_id') ?: ($db['facebookAppId'] ?? null),
            'client_secret' => config('services.facebook.client_secret') ?: ($db['facebookAppSecret'] ?? null),
            'callback_url' => $this->resolveCallbackUrl('facebook', '/api/v1/auth/facebook/callback'),
        ];
    }

    public function isProviderEnabled(string $provider): bool
    {
        $settingKey = $provider === 'google' ? 'googleLoginEnabled' : 'facebookLoginEnabled';
        $value = $this->getSettingValue($settingKey);

        return $value !== 'false';
    }

    public function getProviderStatus(string $provider): array
    {
        $credentials = $provider === 'google' ? $this->getGoogleCredentials() : $this->getFacebookCredentials();

        return [
            'enabled' => $this->isProviderEnabled($provider) && !empty($credentials['client_id']) && !empty($credentials['client_secret']),
            'client_id' => $credentials['client_id'],
        ];
    }

    public function applyProviderConfig(string $provider): void
    {
        $credentials = $provider === 'google' ? $this->getGoogleCredentials() : $this->getFacebookCredentials();

        config()->set("services.{$provider}.client_id", $credentials['client_id'] ?? null);
        config()->set("services.{$provider}.client_secret", $credentials['client_secret'] ?? null);
        config()->set("services.{$provider}.redirect", $credentials['callback_url'] ?? null);
    }

    private function resolveCallbackUrl(string $provider, string $defaultPath): string
    {
        $configured = config("services.{$provider}.redirect");
        if (!empty($configured)) {
            return $configured;
        }

        $request = request();
        $host = $request->server('HTTP_HOST') ?: $request->header('host') ?: $request->getHost();
        $scheme = $request->getScheme();

        if ($host && $scheme) {
            $baseUrl = $scheme . '://' . $host;
        } else {
            $baseUrl = config('app.url') ?: 'http://localhost';
        }

        return rtrim($baseUrl, '/') . $defaultPath;
    }

    /**
     * Get detailed OAuth provider status.
     */
    public function getOAuthProviderStatus(): array
    {
        $google = $this->getGoogleCredentials();
        $facebook = $this->getFacebookCredentials();

        return [
            'google' => [
                'configured' => !empty($google['client_id']) && !empty($google['client_secret']),
                'source' => !empty(config('services.google.client_id')) ? 'env' : (!empty($google['client_id']) ? 'database' : null),
                'has_client_id' => !empty($google['client_id']),
                'has_client_secret' => !empty($google['client_secret']),
            ],
            'facebook' => [
                'configured' => !empty($facebook['client_id']) && !empty($facebook['client_secret']),
                'source' => !empty(config('services.facebook.client_id')) ? 'env' : (!empty($facebook['client_id']) ? 'database' : null),
                'has_client_id' => !empty($facebook['client_id']),
                'has_client_secret' => !empty($facebook['client_secret']),
            ],
        ];
    }

    /**
     * Get cached OAuth settings from database.
     */
    private function getCachedOAuthSettings(): array
    {
        try {
            $keys = ['googleClientId', 'googleClientSecret', 'facebookAppId', 'facebookAppSecret'];
            return Setting::whereIn('key', $keys)->pluck('value', 'key')->toArray();
        } catch (\Exception $e) {
            Log::warning('[OAuthSettings] Failed to fetch DB settings: ' . $e->getMessage());
            return [];
        }
    }

    private function getSettingValue(string $key): ?string
    {
        try {
            return Setting::where('key', $key)->value('value');
        } catch (\Exception $e) {
            Log::warning('[OAuthSettings] Failed to fetch setting ' . $key . ': ' . $e->getMessage());
            return null;
        }
    }
}
