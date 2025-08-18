<?php

namespace ShakewellAgency\PassKitLaravel\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use ShakewellAgency\PassKitLaravel\Models\PassKitSyncLog;
use ShakewellAgency\PassKitLaravel\Models\PassKitProgram;
use ShakewellAgency\PassKitLaravel\Models\PassKitTier;
use ShakewellAgency\PassKitLaravel\Models\PassKitMember;
use ShakewellAgency\PassKitLaravel\Models\PassKitTransaction;
use ShakewellAgency\PassKitLaravel\Models\CardTemplate;
use ShakewellAgency\PassKitLaravel\Models\WalletPass;
use Carbon\Carbon;

class PassKitSyncService
{
    protected PassKitService $passKitService;
    protected array $syncStats = [];
    protected ?PassKitSyncLog $currentSyncLog = null;

    public function __construct(PassKitService $passKitService)
    {
        $this->passKitService = $passKitService;
    }

    /**
     * Perform full synchronization of all PassKit data
     */
    public function performFullSync(array $options = []): array
    {
        $this->initializeSyncLog('full', $options);

        try {
            $this->logMessage('Starting full synchronization');

            // Sync in dependency order
            $this->syncPrograms($options);
            $this->syncTiers($options);
            $this->syncTemplates($options);
            $this->syncMembers($options);
            $this->syncTransactions($options);
            $this->syncWalletPasses($options);

            $this->completeSyncLog();

            return $this->buildSyncResult();

        } catch (\Exception $e) {
            $this->failSyncLog($e->getMessage());
            throw $e;
        }
    }

    /**
     * Perform incremental synchronization based on last sync timestamp
     */
    public function performIncrementalSync(array $options = []): array
    {
        $this->initializeSyncLog('incremental', $options);

        try {
            $lastSync = $this->getLastSuccessfulSync($options['account_id'] ?? null);
            $since = $lastSync ? $lastSync->completed_at : now()->subDays(7);

            $options['since'] = $since;

            $this->logMessage("Starting incremental sync since {$since}");

            // Sync only changed data
            $this->syncChangedMembers($options);
            $this->syncRecentTransactions($options);
            $this->syncChangedWalletPasses($options);

            $this->completeSyncLog();

            return $this->buildSyncResult();

        } catch (\Exception $e) {
            $this->failSyncLog($e->getMessage());
            throw $e;
        }
    }

    /**
     * Sync programs from PassKit API
     */
    public function syncPrograms(array $options = []): array
    {
        $this->initializeSyncLog('programs', $options);

        try {
            $this->logMessage('Syncing programs');

            $programs = $this->passKitService->listPrograms($options);
            $processed = 0;
            $successful = 0;
            $errors = [];

            foreach ($programs as $programData) {
                try {
                    $this->syncSingleProgram($programData, $options);
                    $successful++;
                } catch (\Exception $e) {
                    $errors[] = "Program {$programData['id']}: " . $e->getMessage();
                    Log::error('Program sync failed', [
                        'program_id' => $programData['id'],
                        'error' => $e->getMessage()
                    ]);
                }
                $processed++;

                if ($processed % 10 === 0) {
                    $this->logMessage("Processed {$processed} programs");
                }
            }

            $this->updateSyncStats([
                'total_records' => count($programs),
                'processed_records' => $processed,
                'successful_records' => $successful,
                'failed_records' => count($errors),
            ]);

            $this->completeSyncLog();

            return $this->buildSyncResult($errors);

        } catch (\Exception $e) {
            $this->failSyncLog($e->getMessage());
            throw $e;
        }
    }

    /**
     * Sync members from PassKit API
     */
    public function syncMembers(array $options = []): array
    {
        $this->initializeSyncLog('members', $options);

        try {
            $this->logMessage('Syncing members');

            $chunkSize = $options['chunk_size'] ?? 100;
            $processed = 0;
            $successful = 0;
            $errors = [];

            // Get programs to sync members from
            $programs = $this->getTargetPrograms($options);

            foreach ($programs as $program) {
                $this->logMessage("Syncing members for program: {$program->name}");

                $offset = 0;
                do {
                    $members = $this->passKitService->listMembers($program->passkit_id, [
                        'limit' => $chunkSize,
                        'offset' => $offset,
                        'since' => $options['since'] ?? null,
                        'until' => $options['until'] ?? null,
                    ]);

                    foreach ($members as $memberData) {
                        try {
                            if (!$options['dry_run'] ?? false) {
                                $this->syncSingleMember($memberData, $program, $options);
                            }
                            $successful++;
                        } catch (\Exception $e) {
                            $errors[] = "Member {$memberData['id']}: " . $e->getMessage();
                            Log::error('Member sync failed', [
                                'member_id' => $memberData['id'],
                                'program_id' => $program->passkit_id,
                                'error' => $e->getMessage()
                            ]);
                        }
                        $processed++;
                    }

                    $offset += $chunkSize;

                    if ($processed % 500 === 0) {
                        $this->logMessage("Processed {$processed} members");
                    }

                } while (count($members) === $chunkSize);
            }

            $this->updateSyncStats([
                'total_records' => $processed,
                'processed_records' => $processed,
                'successful_records' => $successful,
                'failed_records' => count($errors),
            ]);

            $this->completeSyncLog();

            return $this->buildSyncResult($errors);

        } catch (\Exception $e) {
            $this->failSyncLog($e->getMessage());
            throw $e;
        }
    }

    /**
     * Sync transactions from PassKit API
     */
    public function syncTransactions(array $options = []): array
    {
        $this->initializeSyncLog('transactions', $options);

        try {
            $this->logMessage('Syncing transactions');

            $chunkSize = $options['chunk_size'] ?? 100;
            $processed = 0;
            $successful = 0;
            $errors = [];

            // Get members to sync transactions for
            $members = $this->getTargetMembers($options);

            foreach ($members as $member) {
                try {
                    $transactions = $this->passKitService->getMemberTransactions($member->passkit_id, [
                        'since' => $options['since'] ?? null,
                        'until' => $options['until'] ?? null,
                        'limit' => $chunkSize,
                    ]);

                    foreach ($transactions as $transactionData) {
                        try {
                            if (!$options['dry_run'] ?? false) {
                                $this->syncSingleTransaction($transactionData, $member, $options);
                            }
                            $successful++;
                        } catch (\Exception $e) {
                            $errors[] = "Transaction {$transactionData['id']}: " . $e->getMessage();
                        }
                        $processed++;
                    }

                } catch (\Exception $e) {
                    $errors[] = "Member {$member->passkit_id} transactions: " . $e->getMessage();
                    Log::error('Member transactions sync failed', [
                        'member_id' => $member->passkit_id,
                        'error' => $e->getMessage()
                    ]);
                }

                if ($processed % 1000 === 0) {
                    $this->logMessage("Processed {$processed} transactions");
                }
            }

            $this->updateSyncStats([
                'total_records' => $processed,
                'processed_records' => $processed,
                'successful_records' => $successful,
                'failed_records' => count($errors),
            ]);

            $this->completeSyncLog();

            return $this->buildSyncResult($errors);

        } catch (\Exception $e) {
            $this->failSyncLog($e->getMessage());
            throw $e;
        }
    }

    /**
     * Sync templates from PassKit API
     */
    public function syncTemplates(array $options = []): array
    {
        $this->initializeSyncLog('templates', $options);

        try {
            $this->logMessage('Syncing templates');

            $templates = $this->passKitService->listTemplates($options);
            $processed = 0;
            $successful = 0;
            $errors = [];

            foreach ($templates as $templateData) {
                try {
                    if (!$options['dry_run'] ?? false) {
                        $this->syncSingleTemplate($templateData, $options);
                    }
                    $successful++;
                } catch (\Exception $e) {
                    $errors[] = "Template {$templateData['id']}: " . $e->getMessage();
                    Log::error('Template sync failed', [
                        'template_id' => $templateData['id'],
                        'error' => $e->getMessage()
                    ]);
                }
                $processed++;
            }

            $this->updateSyncStats([
                'total_records' => count($templates),
                'processed_records' => $processed,
                'successful_records' => $successful,
                'failed_records' => count($errors),
            ]);

            $this->completeSyncLog();

            return $this->buildSyncResult($errors);

        } catch (\Exception $e) {
            $this->failSyncLog($e->getMessage());
            throw $e;
        }
    }

    /**
     * Sync wallet passes
     */
    public function syncWalletPasses(array $options = []): array
    {
        $this->initializeSyncLog('wallet_passes', $options);

        try {
            $this->logMessage('Syncing wallet passes');

            $processed = 0;
            $successful = 0;
            $errors = [];

            // Sync wallet passes for each member
            $members = $this->getTargetMembers($options);

            foreach ($members as $member) {
                try {
                    $passes = $this->passKitService->getMemberPasses($member->passkit_id);

                    foreach ($passes as $passData) {
                        try {
                            if (!$options['dry_run'] ?? false) {
                                $this->syncSingleWalletPass($passData, $member, $options);
                            }
                            $successful++;
                        } catch (\Exception $e) {
                            $errors[] = "Pass {$passData['id']}: " . $e->getMessage();
                        }
                        $processed++;
                    }

                } catch (\Exception $e) {
                    $errors[] = "Member {$member->passkit_id} passes: " . $e->getMessage();
                }
            }

            $this->updateSyncStats([
                'total_records' => $processed,
                'processed_records' => $processed,
                'successful_records' => $successful,
                'failed_records' => count($errors),
            ]);

            $this->completeSyncLog();

            return $this->buildSyncResult($errors);

        } catch (\Exception $e) {
            $this->failSyncLog($e->getMessage());
            throw $e;
        }
    }

    // Protected helper methods

    protected function initializeSyncLog(string $syncType, array $options): void
    {
        $this->syncStats = [];
        
        $this->currentSyncLog = PassKitSyncLog::create([
            'account_id' => $options['account_id'] ?? 0,
            'sync_type' => $syncType,
            'sync_direction' => 'import',
            'status' => 'started',
            'program_id' => $options['program_id'] ?? null,
            'entity_type' => $syncType,
            'started_at' => now(),
            'sync_from_date' => $options['since'] ?? null,
            'sync_to_date' => $options['until'] ?? null,
            'sync_options' => $options,
            'triggered_by' => $options['triggered_by'] ?? 'system',
            'trigger_source' => $options['trigger_source'] ?? 'cron',
        ]);

        $this->logMessage("Initialized sync log: {$this->currentSyncLog->id}");
    }

    protected function completeSyncLog(): void
    {
        if ($this->currentSyncLog) {
            $completedAt = now();
            $duration = $completedAt->diffInSeconds($this->currentSyncLog->started_at);
            $recordsPerSecond = $duration > 0 ? ($this->syncStats['processed_records'] ?? 0) / $duration : 0;

            $this->currentSyncLog->update([
                'status' => 'completed',
                'completed_at' => $completedAt,
                'duration_seconds' => $duration,
                'records_per_second' => $recordsPerSecond,
                'total_records' => $this->syncStats['total_records'] ?? 0,
                'processed_records' => $this->syncStats['processed_records'] ?? 0,
                'successful_records' => $this->syncStats['successful_records'] ?? 0,
                'failed_records' => $this->syncStats['failed_records'] ?? 0,
                'skipped_records' => $this->syncStats['skipped_records'] ?? 0,
            ]);

            $this->logMessage("Completed sync log: {$this->currentSyncLog->id}");
        }
    }

    protected function failSyncLog(string $errorMessage): void
    {
        if ($this->currentSyncLog) {
            $this->currentSyncLog->update([
                'status' => 'failed',
                'error_message' => $errorMessage,
                'completed_at' => now(),
            ]);

            $this->logMessage("Failed sync log: {$this->currentSyncLog->id} - {$errorMessage}");
        }
    }

    protected function updateSyncStats(array $stats): void
    {
        $this->syncStats = array_merge($this->syncStats, $stats);
    }

    protected function logMessage(string $message): void
    {
        Log::info("[PassKit Sync] {$message}", [
            'sync_log_id' => $this->currentSyncLog?->id,
        ]);
    }

    protected function getLastSuccessfulSync(?int $accountId): ?PassKitSyncLog
    {
        $query = PassKitSyncLog::where('status', 'completed')
            ->where('sync_type', 'incremental')
            ->orderBy('completed_at', 'desc');

        if ($accountId) {
            $query->where('account_id', $accountId);
        }

        return $query->first();
    }

    protected function getTargetPrograms(array $options): Collection
    {
        $query = PassKitProgram::where('status', 'active');

        if (!empty($options['account_id'])) {
            $query->where('account_id', $options['account_id']);
        }

        if (!empty($options['program_id'])) {
            $query->where('id', $options['program_id']);
        }

        return $query->get();
    }

    protected function getTargetMembers(array $options): Collection
    {
        $query = PassKitMember::where('status', 'active');

        if (!empty($options['account_id'])) {
            $query->where('account_id', $options['account_id']);
        }

        if (!empty($options['program_id'])) {
            $query->where('program_id', $options['program_id']);
        }

        if (!empty($options['since'])) {
            $query->where('last_activity_at', '>=', $options['since']);
        }

        return $query->get();
    }

    protected function buildSyncResult(array $errors = []): array
    {
        return [
            'sync_log_id' => $this->currentSyncLog?->id,
            'summary' => $this->syncStats,
            'errors' => $errors,
            'warnings' => [],
        ];
    }

    // Individual entity sync methods

    protected function syncSingleProgram(array $programData, array $options): void
    {
        PassKitProgram::updateOrCreate(
            ['passkit_id' => $programData['id']],
            [
                'name' => $programData['name'],
                'description' => $programData['description'] ?? null,
                'program_type' => $programData['type'] ?? 'membership',
                'status' => $programData['status'] ?? 'active',
                'metadata' => $programData,
                'account_id' => $options['account_id'] ?? 0,
            ]
        );
    }

    protected function syncSingleMember(array $memberData, PassKitProgram $program, array $options): void
    {
        PassKitMember::updateOrCreate(
            ['passkit_id' => $memberData['id']],
            [
                'external_id' => $memberData['externalId'] ?? $memberData['id'],
                'user_id' => null, // Would need to map from external_id
                'account_id' => $program->account_id,
                'program_id' => $program->id,
                'tier_id' => $memberData['tierId'] ?? null,
                'email' => $memberData['person']['emailAddress'] ?? null,
                'first_name' => $memberData['person']['forename'] ?? null,
                'last_name' => $memberData['person']['surname'] ?? null,
                'points_balance' => $memberData['points'] ?? 0,
                'status' => $memberData['status'] ?? 'active',
                'enrolled_at' => isset($memberData['createdAt']) ? Carbon::parse($memberData['createdAt']) : now(),
                'last_activity_at' => isset($memberData['updatedAt']) ? Carbon::parse($memberData['updatedAt']) : null,
                'passkit_data' => $memberData,
                'last_sync_at' => now(),
            ]
        );
    }

    protected function syncSingleTransaction(array $transactionData, PassKitMember $member, array $options): void
    {
        PassKitTransaction::updateOrCreate(
            ['passkit_transaction_id' => $transactionData['id']],
            [
                'member_passkit_id' => $member->passkit_id,
                'member_id' => $member->id,
                'account_id' => $member->account_id,
                'transaction_type' => $transactionData['type'] ?? 'earn',
                'points_amount' => $transactionData['amount'] ?? 0,
                'points_balance_before' => $transactionData['balanceBefore'] ?? 0,
                'points_balance_after' => $transactionData['balanceAfter'] ?? 0,
                'description' => $transactionData['description'] ?? null,
                'reference_id' => $transactionData['referenceId'] ?? null,
                'status' => $transactionData['status'] ?? 'completed',
                'processed_at' => isset($transactionData['createdAt']) ? Carbon::parse($transactionData['createdAt']) : now(),
                'passkit_data' => $transactionData,
            ]
        );
    }

    protected function syncSingleTemplate(array $templateData, array $options): void
    {
        CardTemplate::updateOrCreate(
            ['passkit_template_id' => $templateData['id']],
            [
                'name' => $templateData['name'],
                'description' => $templateData['description'] ?? null,
                'template_type' => $templateData['type'] ?? 'membership',
                'account_id' => $options['account_id'] ?? 0,
                'is_active' => $templateData['status'] === 'active',
                'template_data' => $templateData,
                'last_used_at' => isset($templateData['updatedAt']) ? Carbon::parse($templateData['updatedAt']) : null,
            ]
        );
    }

    protected function syncSingleWalletPass(array $passData, PassKitMember $member, array $options): void
    {
        WalletPass::updateOrCreate(
            ['passkit_id' => $passData['id']],
            [
                'member_passkit_id' => $member->passkit_id,
                'user_id' => $member->user_id,
                'account_id' => $member->account_id,
                'program_id' => $member->program_id,
                'pass_data' => $passData,
                'status' => $passData['status'] ?? 'active',
                'is_installed' => $passData['installed'] ?? false,
                'issued_at' => isset($passData['createdAt']) ? Carbon::parse($passData['createdAt']) : now(),
                'last_sync_at' => now(),
            ]
        );
    }

    // Additional sync methods for changed data

    protected function syncChangedMembers(array $options): void
    {
        $this->logMessage('Syncing changed members since last sync');
        $this->syncMembers($options);
    }

    protected function syncRecentTransactions(array $options): void
    {
        $this->logMessage('Syncing recent transactions since last sync');
        $this->syncTransactions($options);
    }

    protected function syncChangedWalletPasses(array $options): void
    {
        $this->logMessage('Syncing changed wallet passes since last sync');
        $this->syncWalletPasses($options);
    }

    protected function syncTiers(array $options = []): void
    {
        $this->logMessage('Syncing tiers');
        
        $programs = $this->getTargetPrograms($options);
        
        foreach ($programs as $program) {
            try {
                $tiers = $this->passKitService->listTiers($program->passkit_id);
                
                foreach ($tiers as $tierData) {
                    PassKitTier::updateOrCreate(
                        ['passkit_id' => $tierData['id']],
                        [
                            'name' => $tierData['name'],
                            'description' => $tierData['description'] ?? null,
                            'program_id' => $program->id,
                            'metadata' => $tierData,
                        ]
                    );
                }
            } catch (\Exception $e) {
                Log::error('Tier sync failed for program', [
                    'program_id' => $program->passkit_id,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
}