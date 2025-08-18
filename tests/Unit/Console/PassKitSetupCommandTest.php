<?php

use ShakewellAgency\PassKitLaravel\Console\Commands\PassKitSetup;
use ShakewellAgency\PassKitLaravel\Services\PassKitService;
use Mockery\MockInterface;

describe('PassKitSetup Console Command', function () {
    beforeEach(function () {
        $this->mockPassKitService = Mockery::mock(PassKitService::class);
        $this->app->instance(PassKitService::class, $this->mockPassKitService);
    });

    afterEach(function () {
        Mockery::close();
    });

    it('can run setup command successfully', function () {
        $this->mockPassKitService->shouldReceive('testConnection')
            ->once()
            ->andReturn(true);

        $this->artisan('passkit:setup')
            ->expectsOutput('PassKit Laravel package setup completed successfully!')
            ->assertExitCode(0);
    });

    it('can setup with certificate files', function () {
        // Create temporary certificate files
        $certPath = storage_path('app/test_cert.pem');
        $keyPath = storage_path('app/test_key.pem');
        
        file_put_contents($certPath, 'test certificate content');
        file_put_contents($keyPath, 'test key content');

        $this->mockPassKitService->shouldReceive('testConnection')
            ->once()
            ->andReturn(true);

        $this->artisan('passkit:setup', [
            '--cert' => $certPath,
            '--key' => $keyPath,
        ])
            ->expectsOutput('Certificate files configured successfully.')
            ->expectsOutput('PassKit Laravel package setup completed successfully!')
            ->assertExitCode(0);

        // Cleanup
        unlink($certPath);
        unlink($keyPath);
    });

    it('handles missing certificate files gracefully', function () {
        $this->artisan('passkit:setup', [
            '--cert' => '/nonexistent/cert.pem',
            '--key' => '/nonexistent/key.pem',
        ])
            ->expectsOutput('Error: Certificate file not found: /nonexistent/cert.pem')
            ->assertExitCode(1);
    });

    it('can setup in testing mode', function () {
        $this->artisan('passkit:setup', ['--testing' => true])
            ->expectsOutput('Running in testing mode - using mock certificates')
            ->expectsOutput('PassKit Laravel package setup completed successfully!')
            ->assertExitCode(0);
    });

    it('validates environment configuration', function () {
        // Temporarily unset required config
        config(['passkit.api_url' => null]);

        $this->artisan('passkit:setup')
            ->expectsOutput('Error: Missing required configuration: PASSKIT_API_URL')
            ->assertExitCode(1);
    });

    it('can force setup when already configured', function () {
        // Set existing configuration
        config([
            'passkit.api_url' => 'https://api.passkit.com',
            'passkit.certificate_path' => 'existing/cert.pem',
        ]);

        $this->mockPassKitService->shouldReceive('testConnection')
            ->once()
            ->andReturn(true);

        $this->artisan('passkit:setup', ['--force' => true])
            ->expectsOutput('Forcing setup - overwriting existing configuration')
            ->expectsOutput('PassKit Laravel package setup completed successfully!')
            ->assertExitCode(0);
    });

    it('skips setup when already configured without force', function () {
        config([
            'passkit.api_url' => 'https://api.passkit.com',
            'passkit.certificate_path' => 'existing/cert.pem',
        ]);

        $this->artisan('passkit:setup')
            ->expectsOutput('PassKit is already configured. Use --force to reconfigure.')
            ->assertExitCode(0);
    });

    it('can setup with interactive prompts', function () {
        $this->artisan('passkit:setup', ['--interactive' => true])
            ->expectsQuestion('Enter PassKit API URL:', 'https://api.passkit.com')
            ->expectsQuestion('Enter certificate file path:', '/path/to/cert.pem')
            ->expectsQuestion('Enter private key file path:', '/path/to/key.pem')
            ->expectsOutput('Interactive setup completed.')
            ->assertExitCode(0);
    });

    it('validates connection after setup', function () {
        $this->mockPassKitService->shouldReceive('testConnection')
            ->once()
            ->andReturn(false);

        $this->artisan('passkit:setup')
            ->expectsOutput('Warning: Could not establish connection to PassKit API')
            ->expectsOutput('Please check your configuration and try again.')
            ->assertExitCode(1);
    });

    it('can publish configuration files', function () {
        $this->artisan('passkit:setup', ['--publish-config' => true])
            ->expectsOutput('Publishing PassKit configuration files...')
            ->expectsOutput('Configuration files published successfully.')
            ->assertExitCode(0);

        // Check that config file was published
        expect(file_exists(config_path('passkit.php')))->toBeTrue();
    });

    it('can run database migrations during setup', function () {
        $this->artisan('passkit:setup', ['--migrate' => true])
            ->expectsOutput('Running PassKit database migrations...')
            ->expectsOutput('Database migrations completed successfully.')
            ->assertExitCode(0);
    });

    it('displays configuration summary', function () {
        config([
            'passkit.api_url' => 'https://api.passkit.com',
            'passkit.certificate_path' => '/path/to/cert.pem',
        ]);

        $this->mockPassKitService->shouldReceive('testConnection')
            ->once()
            ->andReturn(true);

        $this->artisan('passkit:setup', ['--show-config' => true])
            ->expectsOutput('Current PassKit Configuration:')
            ->expectsOutput('API URL: https://api.passkit.com')
            ->expectsOutput('Certificate Path: /path/to/cert.pem')
            ->assertExitCode(0);
    });
});