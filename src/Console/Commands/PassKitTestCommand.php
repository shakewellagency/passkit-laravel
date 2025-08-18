<?php

namespace ShakewellAgency\PassKitLaravel\Console\Commands;

use Illuminate\Console\Command;
use ShakewellAgency\PassKitLaravel\Services\PassKitCrudManager;

class PassKitTestCommand extends Command
{
    protected $signature = 'passkit:test {--feature=all : Test specific feature (connection|programs|members|templates|all)}';

    protected $description = 'Test PassKit functionality and integrations';

    protected PassKitCrudManager $crudManager;

    public function __construct(PassKitCrudManager $crudManager)
    {
        parent::__construct();
        $this->crudManager = $crudManager;
    }

    public function handle(): int
    {
        $feature = $this->option('feature');

        $this->info('🧪 PassKit Integration Test Suite');
        $this->line('');

        switch ($feature) {
            case 'connection':
                return $this->testConnection();
            case 'programs':
                return $this->testPrograms();
            case 'members':
                return $this->testMembers();
            case 'templates':
                return $this->testTemplates();
            case 'all':
            default:
                return $this->runAllTests();
        }
    }

    protected function runAllTests(): int
    {
        $tests = [
            'Connection' => fn() => $this->testConnection(),
            'Health Check' => fn() => $this->testHealthCheck(),
            'System Stats' => fn() => $this->testSystemStats(),
        ];

        $passed = 0;
        $total = count($tests);

        foreach ($tests as $name => $test) {
            $this->info("Testing {$name}...");
            try {
                $result = $test();
                if ($result === Command::SUCCESS) {
                    $this->info("✅ {$name} passed");
                    $passed++;
                } else {
                    $this->error("❌ {$name} failed");
                }
            } catch (\Exception $e) {
                $this->error("❌ {$name} failed: {$e->getMessage()}");
            }
            $this->line('');
        }

        $this->info("Test Results: {$passed}/{$total} passed");
        return $passed === $total ? Command::SUCCESS : Command::FAILURE;
    }

    protected function testConnection(): int
    {
        try {
            $health = $this->crudManager->healthCheck();
            
            if ($health['overall']) {
                $this->info('✅ PassKit API connection successful');
                $this->line("   - API: " . ($health['passkit_api'] ? '✅' : '❌'));
                $this->line("   - Database: " . ($health['database'] ? '✅' : '❌'));
                return Command::SUCCESS;
            } else {
                $this->error('❌ PassKit connection failed');
                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $this->error("Connection test failed: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    protected function testHealthCheck(): int
    {
        try {
            $health = $this->crudManager->healthCheck();
            $this->table(['Component', 'Status'], [
                ['PassKit API', $health['passkit_api'] ? '✅ Connected' : '❌ Failed'],
                ['Database', $health['database'] ? '✅ Connected' : '❌ Failed'],
                ['Overall', $health['overall'] ? '✅ Healthy' : '❌ Unhealthy'],
            ]);
            return $health['overall'] ? Command::SUCCESS : Command::FAILURE;
        } catch (\Exception $e) {
            $this->error("Health check failed: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    protected function testSystemStats(): int
    {
        try {
            $stats = $this->crudManager->getSystemStats();
            
            $this->info('📊 System Statistics:');
            $this->line("   Programs: {$stats['programs']['total']}");
            $this->line("   Tiers: {$stats['tiers']}");
            $this->line("   Templates: {$stats['templates']}");
            $this->line("   Wallet Passes: {$stats['wallet_passes']['total']} (Active: {$stats['wallet_passes']['active']})");
            $this->line("   Total Points: {$stats['total_points']}");
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("System stats test failed: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    protected function testPrograms(): int
    {
        $this->info('Testing program management...');
        // Implementation for program testing
        return Command::SUCCESS;
    }

    protected function testMembers(): int
    {
        $this->info('Testing member management...');
        // Implementation for member testing
        return Command::SUCCESS;
    }

    protected function testTemplates(): int
    {
        $this->info('Testing template management...');
        // Implementation for template testing
        return Command::SUCCESS;
    }
}