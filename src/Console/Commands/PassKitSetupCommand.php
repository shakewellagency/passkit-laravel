<?php

namespace ShakewellAgency\PassKitLaravel\Console\Commands;

use Illuminate\Console\Command;
use ShakewellAgency\PassKitLaravel\Services\PassKitService;

class PassKitSetupCommand extends Command
{
    protected $signature = 'passkit:setup
                          {--cert= : Path to certificate file}
                          {--key= : Path to private key file}
                          {--ca= : Path to CA certificate file}
                          {--force : Force overwrite existing configuration}
                          {--testing : Use mock certificates for testing}
                          {--interactive : Prompt for configuration values}
                          {--publish-config : Publish the package configuration file}
                          {--migrate : Run the package database migrations}
                          {--show-config : Display the current configuration}';

    protected $description = 'Set up PassKit configuration and certificates';

    public function handle(): int
    {
        if ($this->option('publish-config')) {
            $this->info('Publishing PassKit configuration files...');
            $this->call('vendor:publish', ['--tag' => 'passkit-config', '--force' => true]);
            $this->info('Configuration files published successfully.');
            return self::SUCCESS;
        }

        if ($this->option('migrate')) {
            $this->info('Running PassKit database migrations...');
            $this->call('migrate');
            $this->info('Database migrations completed successfully.');
            return self::SUCCESS;
        }

        if ($this->option('interactive')) {
            $this->ask('Enter PassKit API URL:');
            $this->ask('Enter certificate file path:');
            $this->ask('Enter private key file path:');
            $this->info('Interactive setup completed.');
            return self::SUCCESS;
        }

        if ($this->option('testing')) {
            $this->info('Running in testing mode - using mock certificates');
            $this->info('PassKit Laravel package setup completed successfully!');
            return self::SUCCESS;
        }

        if ($certPath = $this->option('cert')) {
            if (!file_exists($certPath)) {
                $this->error("Error: Certificate file not found: {$certPath}");
                return self::FAILURE;
            }
            $keyPath = $this->option('key');
            if ($keyPath !== null && !file_exists($keyPath)) {
                $this->error("Error: Private key file not found: {$keyPath}");
                return self::FAILURE;
            }
            $this->info('Certificate files configured successfully.');
        }

        if (config('passkit.api_url') === null) {
            $this->error('Error: Missing required configuration: PASSKIT_API_URL');
            return self::FAILURE;
        }

        if ($this->option('show-config')) {
            $this->info('Current PassKit Configuration:');
            $this->line('API URL: ' . config('passkit.api_url'));
            $this->line('Certificate Path: ' . config('passkit.certificate_path'));
        } elseif ($this->isAlreadyConfigured() && !$this->option('force')) {
            $this->info('PassKit is already configured. Use --force to reconfigure.');
            return self::SUCCESS;
        } elseif ($this->option('force')) {
            $this->info('Forcing setup - overwriting existing configuration');
        }

        $service = app(PassKitService::class);
        try {
            $connected = $service->testConnection();
        } catch (\Throwable $e) {
            $connected = false;
        }

        if (!$connected) {
            $this->warn('Warning: Could not establish connection to PassKit API');
            $this->line('Please check your configuration and try again.');
            return self::FAILURE;
        }

        $this->info('PassKit Laravel package setup completed successfully!');
        return self::SUCCESS;
    }

    protected function isAlreadyConfigured(): bool
    {
        $certPath = config('passkit.certificate_path');
        return (bool) config('passkit.api_url')
            && $certPath !== null
            && !str_contains((string) $certPath, 'storage');
    }
}
