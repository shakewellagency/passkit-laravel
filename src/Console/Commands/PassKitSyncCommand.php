<?php

namespace ShakewellAgency\PassKitLaravel\Console\Commands;

use Illuminate\Console\Command;
use ShakewellAgency\PassKitLaravel\Services\PassKitSyncService;
use ShakewellAgency\PassKitLaravel\Models\PassKitSyncLog;
use Carbon\Carbon;

class PassKitSyncCommand extends Command
{
    protected $signature = 'passkit:sync 
                          {--type=incremental : Sync type (full|incremental|members|transactions|programs|templates)}
                          {--account= : Specific account ID to sync}
                          {--program= : Specific program ID to sync}
                          {--since= : Sync data since this date (Y-m-d H:i:s)}
                          {--until= : Sync data until this date (Y-m-d H:i:s)}
                          {--dry-run : Show what would be synced without making changes}
                          {--force : Force sync even if recent sync exists}
                          {--chunk-size=100 : Number of records to process per batch}';

    protected $description = 'Sync PassKit data to local database';

    protected PassKitSyncService $syncService;

    public function __construct(PassKitSyncService $syncService)
    {
        parent::__construct();
        $this->syncService = $syncService;
    }

    public function handle(): int
    {
        $syncType = $this->option('type');
        $accountId = $this->option('account');
        $programId = $this->option('program');
        $isDryRun = $this->option('dry-run');
        $force = $this->option('force');

        $this->info("🔄 Starting PassKit sync: {$syncType}");

        // Check for recent syncs unless forced
        if (!$force && $this->hasRecentSync($syncType, $accountId, $programId)) {
            $this->warn('⚠️ Recent sync found. Use --force to override or wait for the scheduled interval.');
            return Command::FAILURE;
        }

        // Prepare sync options
        $options = [
            'account_id' => $accountId,
            'program_id' => $programId,
            'since' => $this->parseDateOption('since'),
            'until' => $this->parseDateOption('until'),
            'chunk_size' => (int) $this->option('chunk-size'),
            'dry_run' => $isDryRun,
            'triggered_by' => 'manual',
            'trigger_source' => 'artisan_command',
        ];

        try {
            $result = match($syncType) {
                'full' => $this->syncService->performFullSync($options),
                'incremental' => $this->syncService->performIncrementalSync($options),
                'members' => $this->syncService->syncMembers($options),
                'transactions' => $this->syncService->syncTransactions($options),
                'programs' => $this->syncService->syncPrograms($options),
                'templates' => $this->syncService->syncTemplates($options),
                default => throw new \InvalidArgumentException("Invalid sync type: {$syncType}")
            };

            $this->displaySyncResults($result, $isDryRun);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Sync failed: {$e->getMessage()}");
            
            if ($this->option('verbose')) {
                $this->line($e->getTraceAsString());
            }

            return Command::FAILURE;
        }
    }

    protected function hasRecentSync(string $syncType, ?string $accountId, ?string $programId): bool
    {
        $query = PassKitSyncLog::where('sync_type', $syncType)
            ->where('status', 'completed')
            ->where('created_at', '>', now()->subHour());

        if ($accountId) {
            $query->where('account_id', $accountId);
        }

        if ($programId) {
            $query->where('program_id', $programId);
        }

        return $query->exists();
    }

    protected function parseDateOption(string $option): ?Carbon
    {
        $value = $this->option($option);
        
        if (!$value) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Exception $e) {
            $this->warn("⚠️ Invalid date format for --{$option}: {$value}");
            return null;
        }
    }

    protected function displaySyncResults(array $result, bool $isDryRun): void
    {
        $prefix = $isDryRun ? '[DRY RUN] ' : '';
        
        $this->info("✅ {$prefix}Sync completed successfully!");
        
        if (isset($result['sync_log_id'])) {
            $this->line("📋 Sync Log ID: {$result['sync_log_id']}");
        }

        if (isset($result['summary'])) {
            $summary = $result['summary'];
            
            $this->table(['Metric', 'Count'], [
                ['Total Records', $summary['total_records'] ?? 0],
                ['Processed', $summary['processed_records'] ?? 0],
                ['Successful', $summary['successful_records'] ?? 0],
                ['Failed', $summary['failed_records'] ?? 0],
                ['Skipped', $summary['skipped_records'] ?? 0],
            ]);

            if (isset($summary['duration_seconds'])) {
                $this->line("⏱️ Duration: {$summary['duration_seconds']} seconds");
            }

            if (isset($summary['records_per_second'])) {
                $this->line("📈 Speed: {$summary['records_per_second']} records/second");
            }
        }

        if (!empty($result['errors'])) {
            $this->warn("⚠️ Encountered {count($result['errors'])} errors:");
            foreach (array_slice($result['errors'], 0, 5) as $error) {
                $this->line("  • {$error}");
            }
            
            if (count($result['errors']) > 5) {
                $remaining = count($result['errors']) - 5;
                $this->line("  ... and {$remaining} more errors");
            }
        }

        if (!empty($result['warnings'])) {
            $this->warn("⚠️ Warnings:");
            foreach ($result['warnings'] as $warning) {
                $this->line("  • {$warning}");
            }
        }
    }
}