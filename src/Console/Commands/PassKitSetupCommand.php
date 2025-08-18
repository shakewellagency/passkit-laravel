<?php

namespace ShakewellAgency\PassKitLaravel\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use ShakewellAgency\PassKitLaravel\Services\PassKitService;

class PassKitSetupCommand extends Command
{
    protected $signature = 'passkit:setup 
                          {--cert= : Path to certificate file}
                          {--key= : Path to private key file}
                          {--ca= : Path to CA certificate file}
                          {--force : Force overwrite existing files}';

    protected $description = 'Set up PassKit configuration and certificates';

    public function handle(): int
    {
        $this->info('Setting up PassKit configuration...');

        // Create storage directories
        $this->createStorageDirectories();

        // Handle certificate files
        $this->handleCertificates();

        // Publish configuration
        $this->publishConfiguration();

        // Test connection
        $this->testConnection();

        $this->info('PassKit setup completed successfully!');
        return Command::SUCCESS;
    }

    protected function createStorageDirectories(): void
    {
        $directories = [
            storage_path('app/passkit'),
            storage_path('app/wallet-passes'),
            storage_path('app/templates'),
        ];

        foreach ($directories as $directory) {
            if (!File::exists($directory)) {
                File::makeDirectory($directory, 0755, true);
                $this->info("Created directory: {$directory}");
            }
        }
    }

    protected function handleCertificates(): void
    {
        $certPath = $this->option('cert');
        $keyPath = $this->option('key');
        $caPath = $this->option('ca');

        if ($certPath && File::exists($certPath)) {
            $destPath = storage_path('app/passkit/certificate.pem');
            File::copy($certPath, $destPath);
            $this->info("Certificate copied to: {$destPath}");
        }

        if ($keyPath && File::exists($keyPath)) {
            $destPath = storage_path('app/passkit/private-key.pem');
            File::copy($keyPath, $destPath);
            $this->info("Private key copied to: {$destPath}");
        }

        if ($caPath && File::exists($caPath)) {
            $destPath = storage_path('app/passkit/ca-certificate.pem');
            File::copy($caPath, $destPath);
            $this->info("CA certificate copied to: {$destPath}");
        }
    }

    protected function publishConfiguration(): void
    {
        $this->call('vendor:publish', [
            '--tag' => 'passkit-config',
            '--force' => $this->option('force')
        ]);

        $this->call('vendor:publish', [
            '--tag' => 'passkit-migrations',
            '--force' => $this->option('force')
        ]);
    }

    protected function testConnection(): void
    {
        $this->info('Testing PassKit connection...');
        
        try {
            $service = app(PassKitService::class);
            $connected = $service->testConnection();
            
            if ($connected) {
                $this->info('✅ PassKit connection successful!');
            } else {
                $this->warn('⚠️ PassKit connection failed. Please check your certificates and configuration.');
            }
        } catch (\Exception $e) {
            $this->error("❌ Connection error: {$e->getMessage()}");
        }
    }
}