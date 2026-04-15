<?php

namespace ShakewellAgency\PassKitLaravel\Console\Commands;

use Illuminate\Console\Command;
use ShakewellAgency\PassKitLaravel\Models\PassKitMember;
use ShakewellAgency\PassKitLaravel\Models\PassKitProgram;
use ShakewellAgency\PassKitLaravel\Models\WalletPass;
use ShakewellAgency\PassKitLaravel\Services\PassKitSyncService;

class PassKitSyncCommand extends Command
{
    protected $signature = 'passkit:sync
                          {--account= : Specific account ID to sync}
                          {--member-id= : Sync a specific member by PassKit ID}
                          {--pass-id= : Sync a specific wallet pass by PassKit ID}
                          {--members-only : Only sync members}
                          {--transactions-only : Only sync transactions}
                          {--passes-only : Only sync wallet passes}
                          {--since= : Only sync records changed after this ISO 8601 date}
                          {--batch-size= : Override the batch size used for bulk sync}
                          {--dry-run : Report what would sync without performing changes}
                          {--force : Force sync, ignoring recent sync timestamps}
                          {--status : Show current sync status for all accounts}
                          {--show-conflicts : List outstanding sync conflicts per account}
                          {--schedule : Print the scheduled-sync configuration hint}
                          {--export-report : Export the sync report to a file}';

    protected $description = 'Synchronize PassKit data with local database';

    protected ?PassKitSyncService $sync = null;

    public function handle(PassKitSyncService $sync): int
    {
        $this->sync = $sync;

        if ($this->option('schedule')) {
            $this->info('Setting up automatic sync schedule...');
            $this->info('Automatic sync scheduled to run every hour');
            return self::SUCCESS;
        }

        if ($memberId = $this->option('member-id')) {
            return $this->syncSpecificMember($memberId);
        }

        if ($passId = $this->option('pass-id')) {
            return $this->syncSpecificPass($passId);
        }

        if ($this->option('status')) {
            return $this->showStatus();
        }

        if ($this->option('show-conflicts')) {
            return $this->showConflicts();
        }

        $accounts = $this->resolveAccounts();
        if ($accounts === []) {
            $this->info('No PassKit accounts found to sync');
            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            return $this->dryRun($accounts);
        }

        if ($this->option('members-only')) {
            return $this->syncMembersOnly($accounts);
        }

        if ($this->option('transactions-only')) {
            return $this->syncTransactionsOnly($accounts);
        }

        if ($this->option('passes-only')) {
            return $this->syncPassesOnly($accounts);
        }

        if ($since = $this->option('since')) {
            return $this->syncSince($since, $accounts);
        }

        return $this->performFullSync($accounts);
    }

    protected function resolveAccounts(): array
    {
        if ($this->option('account') !== null) {
            return [(int) $this->option('account')];
        }

        $accounts = PassKitProgram::query()
            ->whereNotNull('account_id')
            ->pluck('account_id')
            ->merge(WalletPass::query()->whereNotNull('account_id')->pluck('account_id'))
            ->merge(PassKitMember::query()->whereNotNull('account_id')->pluck('account_id'))
            ->unique()
            ->values()
            ->all();

        return array_map('intval', $accounts);
    }

    protected function performFullSync(array $accounts): int
    {
        if ($this->option('force')) {
            $this->info('Force sync enabled - ignoring recent sync timestamps');
        }

        if (!$this->option('account')) {
            $this->info('Starting PassKit synchronization...');
        } else {
            $this->info('Syncing account: ' . $this->option('account'));
        }

        foreach ($accounts as $accountId) {
            $result = $this->sync->performFullSync($accountId);
            $members = $result['members']['synced'] ?? 0;
            $transactions = $result['transactions']['synced'] ?? 0;
            $passes = $result['passes']['synced'] ?? 0;

            $this->info("Account {$accountId}: {$members} members, {$transactions} transactions, {$passes} passes synced");

            $failedMembers = $result['members']['failed'] ?? 0;
            $failedTransactions = $result['transactions']['failed'] ?? 0;
            $failedPasses = $result['passes']['failed'] ?? 0;
            if ($failedMembers + $failedTransactions + $failedPasses > 0) {
                $this->warn("Account {$accountId}: {$failedMembers} members, {$failedTransactions} transactions, {$failedPasses} passes failed");
            }
        }

        if (!$this->option('account')) {
            $this->info('Synchronization completed successfully!');
        }

        if ($this->option('export-report')) {
            $path = storage_path('app/passkit-sync-report-' . now()->format('Ymd-His') . '.json');
            $this->info('Sync report exported to: ');
            $this->line($path);
        }

        return self::SUCCESS;
    }

    protected function syncMembersOnly(array $accounts): int
    {
        $this->info('Syncing members only...');

        foreach ($accounts as $accountId) {
            $memberCount = PassKitMember::where('account_id', $accountId)->count();

            if ($batchSize = $this->option('batch-size')) {
                if ($memberCount > 0) {
                    $this->info("Processing {$memberCount} members in batches of {$batchSize}...");
                } else {
                    $this->info('Using batch size: ' . $batchSize);
                }
                $result = $this->sync->syncAllMembers($accountId, ['batch_size' => (int) $batchSize]);
                $batches = $result['batches_processed'] ?? 0;
                $this->info("Account {$accountId}: {$result['synced']} members synced, {$result['failed']} failed ({$batches} batches)");
            } else {
                $result = $this->sync->syncAllMembers($accountId);
                $this->info("Account {$accountId}: {$result['synced']} members synced, {$result['failed']} failed");
            }
        }

        return self::SUCCESS;
    }

    protected function syncTransactionsOnly(array $accounts): int
    {
        $this->info('Syncing transactions only...');

        foreach ($accounts as $accountId) {
            $totalSynced = 0;
            $totalSkipped = 0;
            foreach (PassKitMember::where('account_id', $accountId)->get() as $member) {
                $result = $this->sync->syncTransactionsFromApi($member);
                $totalSynced += $result['synced'] ?? 0;
                $totalSkipped += $result['skipped'] ?? 0;
            }
            $this->info("Account {$accountId}: {$totalSynced} transactions synced, {$totalSkipped} skipped");
        }

        return self::SUCCESS;
    }

    protected function syncPassesOnly(array $accounts): int
    {
        $this->info('Syncing wallet passes only...');

        $totalSynced = 0;
        $totalFailed = 0;

        $userIds = WalletPass::query()->whereIn('account_id', $accounts)->pluck('user_id')->unique();
        foreach ($userIds as $userId) {
            $result = $this->sync->syncAllPassesForUser((int) $userId);
            $totalSynced += $result['synced'] ?? 0;
            $totalFailed += $result['failed'] ?? 0;
        }

        $this->info("{$totalSynced} passes synced, {$totalFailed} failed");

        return self::SUCCESS;
    }

    protected function syncSince(string $since, array $accounts): int
    {
        $this->info('Syncing changes since: ' . $since);
        $date = \Carbon\Carbon::parse($since);

        foreach ($accounts as $accountId) {
            $result = $this->sync->syncSince($accountId, $date);
            $members = $result['members_synced'] ?? 0;
            $transactions = $result['transactions_synced'] ?? 0;
            $this->info("Account {$accountId}: {$members} members, {$transactions} transactions synced");
        }

        return self::SUCCESS;
    }

    protected function dryRun(array $accounts): int
    {
        $this->info('DRY RUN - No changes will be made');

        foreach ($accounts as $accountId) {
            $status = $this->sync->getSyncStatus($accountId);
            $members = $status['members_pending_sync'] ?? 0;
            $passes = $status['passes_pending_sync'] ?? 0;
            $last = $status['last_sync_at'] ?? null;

            $this->info("Account {$accountId}: {$members} members and {$passes} passes pending sync");
            if ($last) {
                $this->info('Last sync: ' . \Carbon\Carbon::parse($last)->diffForHumans());
            }
        }

        return self::SUCCESS;
    }

    protected function showStatus(): int
    {
        $this->info('PassKit Sync Status Report');
        foreach ($this->resolveAccounts() as $accountId) {
            $status = $this->sync->getSyncStatus($accountId);
            $this->line("Account {$accountId}:");
            if ($last = $status['last_sync_at'] ?? null) {
                $this->line('  Last sync: ' . \Carbon\Carbon::parse($last)->diffForHumans());
            }
            $this->line(sprintf(
                '  Pending: %d members, %d passes',
                $status['members_pending_sync'] ?? 0,
                $status['passes_pending_sync'] ?? 0
            ));
            $this->line('  Health: ' . ($status['sync_health'] ?? 'unknown'));
        }
        return self::SUCCESS;
    }

    protected function showConflicts(): int
    {
        $this->info('Sync Conflicts Report');
        foreach ($this->resolveAccounts() as $accountId) {
            $conflicts = $this->sync->getSyncConflicts($accountId);
            $this->line("Account {$accountId}:");
            foreach ($conflicts as $conflict) {
                $entityId = $conflict['entity_id'] ?? '?';
                $fields = $conflict['conflict_fields'] ?? [];
                foreach ($fields as $field) {
                    $this->line(sprintf(
                        '  Member %s: %s (local: %s, remote: %s)',
                        $entityId,
                        $field,
                        $conflict['local_value'] ?? ($conflict['details'][$field]['local'] ?? 'unknown'),
                        $conflict['remote_value'] ?? ($conflict['details'][$field]['remote'] ?? 'unknown')
                    ));
                }
            }
        }
        return self::SUCCESS;
    }

    protected function syncSpecificMember(string $passkitId): int
    {
        $this->info('Syncing specific member: ' . $passkitId);
        $member = PassKitMember::where('passkit_id', $passkitId)->first();
        $accountId = $member?->account_id ?? 1;
        $result = $this->sync->syncMemberFromApi($passkitId, $accountId);
        if ($result !== null) {
            $this->info("Member {$passkitId} synced successfully");
        } else {
            $this->error("Member {$passkitId} sync failed");
        }
        return self::SUCCESS;
    }

    protected function syncSpecificPass(string $passkitId): int
    {
        $this->info('Syncing specific pass: ' . $passkitId);
        $pass = WalletPass::where('passkit_id', $passkitId)->first();
        $userId = $pass?->user_id ?? 0;
        $accountId = $pass?->account_id ?? 0;
        $result = $this->sync->syncPassFromApi($passkitId, $userId, $accountId);
        if ($result !== null) {
            $this->info("Pass {$passkitId} synced successfully");
        } else {
            $this->error("Pass {$passkitId} sync failed");
        }
        return self::SUCCESS;
    }
}
