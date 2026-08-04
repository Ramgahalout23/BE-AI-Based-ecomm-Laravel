<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\OAuthSettingsService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

class AuthOAuthSettingsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('module')->nullable();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('settings');

        parent::tearDown();
    }

    public function test_google_oauth_status_uses_admin_settings_when_present(): void
    {
        config()->set('services.google.client_id', null);
        config()->set('services.google.client_secret', null);
        config()->set('services.facebook.client_id', null);
        config()->set('services.facebook.client_secret', null);

        Setting::create(['key' => 'googleLoginEnabled', 'value' => 'true']);
        Setting::create(['key' => 'googleClientId', 'value' => 'test-google-client-id']);
        Setting::create(['key' => 'googleClientSecret', 'value' => 'test-google-client-secret']);
        Setting::create(['key' => 'facebookLoginEnabled', 'value' => 'false']);

        $response = $this->getJson('/api/v1/auth/oauth/status');

        $response->assertOk()
            ->assertJsonPath('data.providers.google.enabled', true)
            ->assertJsonPath('data.providers.google.client_id', 'test-google-client-id')
            ->assertJsonPath('data.providers.facebook.enabled', false);
    }

    public function test_google_oauth_redirects_browser_to_google_for_web_flow(): void
    {
        config()->set('services.google.client_id', 'test-google-client-id');
        config()->set('services.google.client_secret', 'test-google-client-secret');
        config()->set('app.frontend_url', 'http://localhost:5173');

        Setting::create(['key' => 'googleLoginEnabled', 'value' => 'true']);
        Setting::create(['key' => 'googleClientId', 'value' => 'test-google-client-id']);
        Setting::create(['key' => 'googleClientSecret', 'value' => 'test-google-client-secret']);

        $redirectResponse = Mockery::mock();
        $redirectResponse->shouldReceive('getTargetUrl')->andReturn('https://accounts.google.com/o/oauth2/auth');

        $driver = Mockery::mock();
        $driver->shouldReceive('stateless')->andReturnSelf();
        $driver->shouldReceive('redirect')->andReturn($redirectResponse);

        Socialite::shouldReceive('driver')->with('google')->andReturn($driver);

        $response = $this->get('/api/v1/auth/google');

        $response->assertStatus(302)
            ->assertRedirect('https://accounts.google.com/o/oauth2/auth');
    }

    public function test_google_callback_url_uses_current_request_host_with_port(): void
    {
        config()->set('services.google.client_id', null);
        config()->set('services.google.client_secret', null);
        config()->set('services.google.redirect', null);
        config()->set('app.url', 'http://localhost');

        $this->app['request']->server->set('HTTP_HOST', 'localhost:8000');
        $this->app['request']->server->set('REQUEST_SCHEME', 'http');

        $service = new OAuthSettingsService();
        $credentials = $service->getGoogleCredentials();

        $this->assertSame('http://localhost:8000/api/v1/auth/google/callback', $credentials['callback_url']);
    }

    public function test_browser_oauth_callback_redirects_back_to_frontend_on_failure(): void
    {
        config()->set('services.google.client_id', 'test-google-client-id');
        config()->set('services.google.client_secret', 'test-google-client-secret');
        config()->set('app.frontend_url', 'http://localhost:5173');
        config()->set('services.google.redirect', 'http://localhost:8000/api/v1/auth/google/callback');

        Setting::create(['key' => 'googleLoginEnabled', 'value' => 'true']);

        $driver = Mockery::mock();
        $driver->shouldReceive('stateless')->andReturnSelf();
        $driver->shouldReceive('user')->andThrow(new \Exception('invalid_grant'));

        Socialite::shouldReceive('driver')->with('google')->andReturn($driver);

        $response = $this->get('/api/v1/auth/google/callback');

        $response->assertStatus(302)
            ->assertRedirectContains('oauth_error=google_login_failed')
            ->assertRedirectContains('message=');
    }
}
