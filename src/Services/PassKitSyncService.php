<?php

namespace ShakewellAgency\PassKitLaravel\Services;

use ShakewellAgency\PassKitLaravel\Models\PassKitAuditLog;
use ShakewellAgency\PassKitLaravel\Models\PassKitMember;
use ShakewellAgency\PassKitLaravel\Models\PassKitSyncLog;
use ShakewellAgency\PassKitLaravel\Models\PassKitTransaction;
use ShakewellAgency\PassKitLaravel\Models\WalletPass;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PassKitSyncService
{
    protected PassKitService $passKitService;
    protected ?PassKitCrudManager $crudManager;

    public function __construct(PassKitService $passKitService, ?PassKitCrudManager $crudManager = null)
    {
        $this->passKitService = $passKitService;
        $this->crudManager = $crudManager;
    }

    public function syncMemberFromApi(string $passkitId, int $accountId, array $options = []): ?PassKitMember
    {
        $maxRetries = max(1, (int) ($options['max_retries'] ?? 1));
        $attempt = 0;
        $data = null;
        $lastError = null;

        while ($attempt < $maxRetries) {
            $attempt++;
            try {
                $data = $this->passKitService->getMember($passkitId);
                $lastError = null;
                break;
            } catch (\Throwable $e) {
                $lastError = $e;
                Log::warning('syncMemberFromApi attempt failed', [
                    'passkit_id' => $passkitId,
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($data === null) {
            $this->logSyncFailure('member', $passkitId, $accountId, $lastError?->getMessage() ?? 'Unknown error');
            return null;
        }

        // PassKitService::getMember() returns either a protobuf message
        // (real gRPC path) or an array (testingMode mock). Normalise to
        // array before handing to mapApiMemberToAttributes(array $data).
        if ($data instanceof \Google\Protobuf\Internal\Message) {
            $data = json_decode($data->serializeToJsonString(), true) ?? [];
        }

        $member = PassKitMember::where('passkit_id', $passkitId)->first();
        $attributes = $this->mapApiMemberToAttributes($data, $accountId);

        if ($member) {
            $member->update($attributes);
        } else {
            $member = PassKitMember::create(array_merge([
                'passkit_id' => $passkitId,
                'enrolled_at' => now(),
                'status' => 'active',
                'program_id' => 0,
            ], $attributes));
        }

        $member->update(['last_sync_at' => now(), 'sync_pending' => false]);

        return $member->fresh();
    }

    public function syncMemberToApi(PassKitMember $member): array
    {
        $payload = [
            'externalId' => $member->external_id,
            'email' => $member->email,
            'firstName' => $member->first_name,
            'lastName' => $member->last_name,
            'points' => $member->points_balance,
            'tier' => $member->tier_id,
        ];

        return (array) $this->passKitService->updateMember($member->passkit_id, $payload);
    }

    public function syncAllMembers(int $accountId, array $options = []): array
    {
        $batchSize = (int) ($options['batch_size'] ?? 100);
        $members = PassKitMember::byAccount($accountId)->get();

        $synced = 0;
        $failed = 0;

        $skipped = 0;
        foreach ($members as $member) {
            // PassKitMember rows can exist locally without a remote
            // passkit_id (signup created the member ahead of upstream
            // provisioning, or the upstream record was deleted and the
            // local row was kept). syncMemberFromApi requires a string
            // id by signature; skip such rows with a warning so the
            // rest of the batch still processes.
            if (empty($member->passkit_id)) {
                Log::warning('syncAllMembers skipping member with no passkit_id', [
                    'member_id' => $member->id,
                    'account_id' => $accountId,
                ]);
                $skipped++;
                continue;
            }

            $result = $this->syncMemberFromApi($member->passkit_id, $accountId);
            if ($result !== null) {
                $synced++;
            } else {
                $failed++;
            }
        }

        return [
            'synced' => $synced,
            'failed' => $failed,
            'skipped' => $skipped,
            'batches_processed' => $members->count() > 0 ? (int) ceil($members->count() / max(1, $batchSize)) : 0,
        ];
    }

    public function detectSyncConflicts(PassKitMember $member): array
    {
        try {
            $apiData = $this->passKitService->getMember($member->passkit_id);
        } catch (\Throwable $e) {
            return [];
        }

        $conflicts = [];
        if (isset($apiData['points']) && (int) $apiData['points'] !== (int) $member->points_balance) {
            $conflicts['points_balance'] = [
                'local' => $member->points_balance,
                'remote' => (int) $apiData['points'],
            ];
        }

        return $conflicts;
    }

    public function syncTransactionsFromApi(PassKitMember $member): array
    {
        $transactions = (array) $this->passKitService->getMemberTransactions($member->passkit_id);

        $synced = 0;
        $skipped = 0;

        foreach ($transactions as $data) {
            $passkitTransactionId = $data['id'] ?? null;
            if ($passkitTransactionId === null) {
                $skipped++;
                continue;
            }

            $exists = PassKitTransaction::where('passkit_transaction_id', $passkitTransactionId)->exists();
            if ($exists) {
                $skipped++;
                continue;
            }

            PassKitTransaction::create([
                'passkit_transaction_id' => $passkitTransactionId,
                'member_id' => $member->id,
                'member_passkit_id' => $member->passkit_id,
                'account_id' => $member->account_id,
                'transaction_type' => $data['type'] ?? 'earn',
                'points_amount' => (int) ($data['points'] ?? 0),
                'points_balance_before' => $member->points_balance,
                'points_balance_after' => $member->points_balance + (int) ($data['points'] ?? 0),
                'description' => $data['description'] ?? null,
                'status' => $data['status'] ?? 'completed',
                'processed_at' => $data['created_at'] ?? now(),
            ]);

            $synced++;
        }

        return ['synced' => $synced, 'skipped' => $skipped];
    }

    public function syncTransactionToApi(PassKitTransaction $transaction): array
    {
        $payload = [
            'type' => $transaction->transaction_type,
            'points' => $transaction->points_amount,
            'description' => $transaction->description,
        ];

        return (array) $this->passKitService->updateTransaction($transaction->passkit_transaction_id, $payload);
    }

    public function syncPassFromApi(string $passkitId, ?int $userId, int $accountId): ?WalletPass
    {
        try {
            $data = $this->passKitService->getPass($passkitId);
        } catch (\Throwable $e) {
            $this->logSyncFailure('wallet_pass', $passkitId, $accountId, $e->getMessage());
            return null;
        }

        $pass = WalletPass::where('passkit_id', $passkitId)->first();
        $attributes = [
            'status' => $data['status'] ?? 'active',
            'pass_data' => $data['data'] ?? [],
            'is_installed' => (bool) ($data['installed'] ?? false),
            'device_type' => $data['device_type'] ?? null,
            'member_passkit_id' => $data['member_id'] ?? null,
        ];

        if ($pass) {
            $pass->update($attributes);
        } else {
            // user_id is only required when creating a brand-new local
            // pass record. The bulk-sync caller (performFullSync) iterates
            // existing local rows so the update branch is the common path.
            // If we hit the create branch with a null user_id we have no
            // way to attach the pass to a user — skip with a clear log
            // rather than coercing to 0 and creating an orphan.
            if ($userId === null) {
                $this->logSyncFailure(
                    'wallet_pass',
                    $passkitId,
                    $accountId,
                    'Cannot create new local WalletPass: userId is null and no existing local pass to update.'
                );
                return null;
            }

            $pass = WalletPass::create(array_merge([
                'passkit_id' => $passkitId,
                'user_id' => $userId,
                'account_id' => $accountId,
                'issued_at' => now(),
            ], $attributes));
        }

        $pass->update(['last_sync_at' => now(), 'sync_pending' => false]);

        return $pass->fresh();
    }

    public function syncPassToApi(WalletPass $pass): array
    {
        $payload = [
            'status' => $pass->status,
            'data' => $pass->pass_data,
        ];

        return (array) $this->passKitService->updatePass($pass->passkit_id, $payload);
    }

    public function syncAllPassesForUser(int $userId): array
    {
        $passes = WalletPass::where('user_id', $userId)->get();

        $synced = 0;
        $failed = 0;

        foreach ($passes as $pass) {
            $result = $this->syncPassFromApi($pass->passkit_id, $userId, $pass->account_id);
            if ($result !== null) {
                $synced++;
            } else {
                $failed++;
            }
        }

        return ['synced' => $synced, 'failed' => $failed];
    }

    public function performFullSync(int $accountId): array
    {
        $log = PassKitSyncLog::create([
            'account_id' => $accountId,
            'sync_type' => 'full',
            'sync_direction' => 'import',
            'status' => 'in_progress',
            'started_at' => now(),
            'trigger_source' => app()->runningInConsole() ? 'cron' : 'api',
        ]);

        try {
            $memberResults = $this->syncAllMembers($accountId);

            $transactionSynced = 0;
            $transactionSkipped = 0;
            foreach (PassKitMember::byAccount($accountId)->get() as $member) {
                $result = $this->syncTransactionsFromApi($member);
                $transactionSynced += $result['synced'];
                $transactionSkipped += $result['skipped'];
            }

            $passSynced = 0;
            $passFailed = 0;
            foreach (WalletPass::where('account_id', $accountId)->get() as $pass) {
                $result = $this->syncPassFromApi($pass->passkit_id, $pass->user_id, $accountId);
                if ($result !== null) {
                    $passSynced++;
                } else {
                    $passFailed++;
                }
            }

            $summary = [
                'members' => $memberResults,
                'transactions' => ['synced' => $transactionSynced, 'skipped' => $transactionSkipped],
                'passes' => ['synced' => $passSynced, 'failed' => $passFailed],
            ];

            $memberSynced = (int) ($memberResults['synced'] ?? 0);
            $memberFailed = (int) ($memberResults['failed'] ?? 0);

            $totalSucceeded = $memberSynced + $transactionSynced + $passSynced;
            $totalFailed = $memberFailed + $passFailed;
            $totalProcessed = $totalSucceeded + $totalFailed + $transactionSkipped;

            $completedAt = now();
            $duration = max(0, $completedAt->diffInSeconds($log->started_at));

            $log->update([
                'status' => 'completed',
                'completed_at' => $completedAt,
                'duration_seconds' => $duration,
                'total_records' => $totalProcessed,
                'processed_records' => $totalProcessed,
                'successful_records' => $totalSucceeded,
                'failed_records' => $totalFailed,
                'skipped_records' => $transactionSkipped,
                'records_per_second' => $duration > 0 ? round($totalProcessed / $duration, 2) : null,
                'changes_summary' => $summary,
            ]);

            return $summary;
        } catch (\Throwable $e) {
            $log->update([
                'status' => 'failed',
                'completed_at' => now(),
                'error_message' => $e->getMessage(),
                'error_details' => [
                    'class' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ],
            ]);

            throw $e;
        }
    }

    public function syncSince(int $accountId, $since): array
    {
        $data = (array) $this->passKitService->getMembersSince((string) $since);

        $synced = 0;
        foreach ($data as $apiMember) {
            $member = PassKitMember::where('passkit_id', $apiMember['id'] ?? '')->first();
            if ($member) {
                $member->update($this->mapApiMemberToAttributes($apiMember, $accountId));
                $synced++;
            }
        }

        return ['members_synced' => $synced];
    }

    public function getSyncStatus(int $accountId): array
    {
        $membersPending = PassKitMember::byAccount($accountId)->where('sync_pending', true)->count();
        $passesPending = WalletPass::where('account_id', $accountId)->where('sync_pending', true)->count();
        $lastSync = PassKitMember::byAccount($accountId)->max('last_sync_at');

        return [
            'last_sync_at' => $lastSync,
            'members_pending_sync' => $membersPending,
            'passes_pending_sync' => $passesPending,
            'sync_health' => ($membersPending + $passesPending) === 0 ? 'healthy' : 'pending',
        ];
    }

    public function markSyncPending($entity): void
    {
        $entity->update(['sync_pending' => true]);
    }

    public function clearSyncPending($entity): void
    {
        $entity->update(['sync_pending' => false, 'last_sync_at' => now()]);
    }

    public function getSyncConflicts(int $accountId): array
    {
        $conflicts = [];

        foreach (PassKitMember::byAccount($accountId)->get() as $member) {
            $memberConflicts = $this->detectSyncConflicts($member);
            if (!empty($memberConflicts)) {
                $conflicts[] = [
                    'entity_type' => 'member',
                    'entity_id' => $member->id,
                    'passkit_id' => $member->passkit_id,
                    'conflict_fields' => array_keys($memberConflicts),
                    'details' => $memberConflicts,
                ];
            }
        }

        return $conflicts;
    }

    protected function mapApiMemberToAttributes(array $data, int $accountId): array
    {
        $attrs = ['account_id' => $accountId];

        $map = [
            'externalId' => 'external_id',
            'email' => 'email',
            'firstName' => 'first_name',
            'lastName' => 'last_name',
            'points' => 'points_balance',
            'tier' => 'tier_id',
            'status' => 'status',
        ];

        foreach ($map as $apiKey => $column) {
            if (array_key_exists($apiKey, $data)) {
                $attrs[$column] = $data[$apiKey];
            }
        }

        if (isset($data['points'])) {
            $attrs['points_balance'] = (int) $data['points'];
        }

        return $attrs;
    }

    protected function logSyncFailure(string $entityType, string $passkitId, int $accountId, string $error): void
    {
        $entityRecord = null;
        if ($entityType === 'member') {
            $entityRecord = PassKitMember::where('passkit_id', $passkitId)->first();
        } elseif ($entityType === 'wallet_pass') {
            $entityRecord = WalletPass::where('passkit_id', $passkitId)->first();
        }

        PassKitAuditLog::create([
            'account_id' => $accountId,
            'event_type' => 'sync_failed',
            'entity_type' => $entityType,
            'entity_id' => $entityRecord?->id,
            'passkit_id' => $passkitId,
            'source' => 'sync',
            'operation' => 'sync',
            'status' => 'failed',
            'error_message' => $error,
            'correlation_id' => (string) Str::uuid(),
        ]);
    }
}
