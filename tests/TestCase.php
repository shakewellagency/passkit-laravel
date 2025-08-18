<?php

namespace ShakewellAgency\PassKitLaravel\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use ShakewellAgency\PassKitLaravel\PassKitServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        
        // Set up test configuration
        $this->app['config']->set('passkit.api.host', 'grpc.pub2.passkit.io');
        $this->app['config']->set('passkit.api.port', '443');
        $this->app['config']->set('passkit.testing_mode', true);
        $this->app['config']->set('passkit.certificates.cert_path', storage_path('app/passkit/certificate.pem'));
        $this->app['config']->set('passkit.certificates.key_path', storage_path('app/passkit/private-key.pem'));
        $this->app['config']->set('passkit.sync.enabled', true);
        $this->app['config']->set('passkit.audit.enabled', true);
        
        // Set up database configuration
        $this->app['config']->set('database.default', 'testing');
        $this->app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function getPackageProviders($app): array
    {
        return [
            PassKitServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'PassKit' => 'ShakewellAgency\PassKitLaravel\Facades\PassKit',
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Define environment setup
    }

    /**
     * Create a test user for testing
     */
    protected function createTestUser(): object
    {
        return (object) [
            'id' => 1,
            'name' => 'Test User',
            'email' => 'test@example.com',
        ];
    }

    /**
     * Create test account data
     */
    protected function createTestAccount(): array
    {
        return [
            'id' => 1,
            'name' => 'Test Account',
            'settings' => [],
        ];
    }

    /**
     * Mock PassKit API responses
     */
    protected function mockPassKitApi(): void
    {
        // This will be implemented in individual test files as needed
    }
}