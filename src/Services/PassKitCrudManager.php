<?php

namespace ShakewellAgency\PassKitLaravel\Services;

use ShakewellAgency\PassKitLaravel\Models\PassKitProgram;
use ShakewellAgency\PassKitLaravel\Models\PassKitMember;
use ShakewellAgency\PassKitLaravel\Models\PassKitTransaction;
use ShakewellAgency\PassKitLaravel\Models\WalletPass;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class PassKitCrudManager
{
    protected PassKitService $passKitService;

    public function __construct(PassKitService $passKitService)
    {
        $this->passKitService = $passKitService;
    }

    // Programs
    public function createProgram(array $data): PassKitProgram
    {
        return PassKitProgram::create($this->withProgramDefaults($data));
    }

    public function updateProgram(int $id, array $data): ?PassKitProgram
    {
        $program = PassKitProgram::find($id);
        if ($program === null) {
            return null;
        }
        $program->update($data);
        return $program->fresh();
    }

    public function deleteProgram(int $id): bool
    {
        $program = PassKitProgram::find($id);
        return $program !== null && $program->delete();
    }

    public function getProgram(int $id): ?PassKitProgram
    {
        return PassKitProgram::find($id);
    }

    /**
     * @return Collection<int, PassKitProgram>|LengthAwarePaginator
     */
    public function listPrograms(int $accountId, array $options = [])
    {
        $query = PassKitProgram::byAccount($accountId);

        if (isset($options['status'])) {
            $query->where('status', $options['status']);
        }

        if (isset($options['per_page'])) {
            return $query->paginate((int) $options['per_page']);
        }

        return $query->get();
    }

    // Members
    public function createMember(array $data): PassKitMember
    {
        $this->validateMemberData($data);
        return PassKitMember::create($this->withMemberDefaults($data));
    }

    public function updateMember(int $id, array $data): ?PassKitMember
    {
        $member = PassKitMember::find($id);
        if ($member === null) {
            return null;
        }
        $member->update($data);
        return $member->fresh();
    }

    public function deleteMember(int $id): bool
    {
        $member = PassKitMember::find($id);
        return $member !== null && $member->delete();
    }

    public function getMember(int $id): ?PassKitMember
    {
        return PassKitMember::find($id);
    }

    public function findMemberByPassKitId(string $passkitId): ?PassKitMember
    {
        return PassKitMember::where('passkit_id', $passkitId)->first();
    }

    public function findMemberByExternalId(string $externalId, int $accountId): ?PassKitMember
    {
        return PassKitMember::where('external_id', $externalId)
            ->where('account_id', $accountId)
            ->first();
    }

    /**
     * @return Collection<int, PassKitMember>|LengthAwarePaginator
     */
    public function listMembers(int $accountId, array $options = [])
    {
        $query = PassKitMember::byAccount($accountId);

        foreach (['status', 'program_id', 'tier_id'] as $key) {
            if (isset($options[$key])) {
                $query->where($key, $options[$key]);
            }
        }

        if (isset($options['per_page'])) {
            return $query->paginate((int) $options['per_page']);
        }

        return $query->get();
    }

    public function searchMembers(string $term, int $accountId): Collection
    {
        return PassKitMember::byAccount($accountId)
            ->where(function ($query) use ($term) {
                $query->where('email', 'like', "%{$term}%")
                    ->orWhere('first_name', 'like', "%{$term}%")
                    ->orWhere('last_name', 'like', "%{$term}%")
                    ->orWhere('external_id', 'like', "%{$term}%");
            })
            ->get();
    }

    public function getMemberStatistics(int $accountId): array
    {
        $base = PassKitMember::byAccount($accountId);

        return [
            'total' => (clone $base)->count(),
            'active' => (clone $base)->where('status', 'active')->count(),
            'inactive' => (clone $base)->where('status', 'inactive')->count(),
        ];
    }

    public function batchCreateMembers(array $members): Collection
    {
        return DB::transaction(function () use ($members) {
            $created = new Collection();
            foreach ($members as $data) {
                $created->push($this->createMember($data));
            }
            return $created;
        });
    }

    public function batchUpdateMemberPoints(array $updates): bool
    {
        return DB::transaction(function () use ($updates) {
            foreach ($updates as $update) {
                if (!isset($update['id'])) {
                    continue;
                }
                PassKitMember::where('id', $update['id'])
                    ->update(['points_balance' => $update['points_balance'] ?? 0]);
            }
            return true;
        });
    }

    // Transactions
    public function createTransaction(array $data): PassKitTransaction
    {
        return PassKitTransaction::create($this->withTransactionDefaults($data));
    }

    public function updateTransaction(int $id, array $data): ?PassKitTransaction
    {
        $transaction = PassKitTransaction::find($id);
        if ($transaction === null) {
            return null;
        }
        $transaction->update($data);
        return $transaction->fresh();
    }

    public function deleteTransaction(int $id): bool
    {
        $transaction = PassKitTransaction::find($id);
        return $transaction !== null && $transaction->delete();
    }

    public function getTransaction(int $id): ?PassKitTransaction
    {
        return PassKitTransaction::find($id);
    }

    /**
     * @return Collection<int, PassKitTransaction>|LengthAwarePaginator
     */
    public function listTransactions(array $filters = [])
    {
        $query = PassKitTransaction::query();

        foreach (['member_id', 'member_passkit_id', 'account_id', 'transaction_type', 'status'] as $key) {
            if (isset($filters[$key])) {
                $query->where($key, $filters[$key]);
            }
        }

        if (isset($filters['per_page'])) {
            return $query->paginate((int) $filters['per_page']);
        }

        return $query->get();
    }

    public function getTransactionStatistics(int $accountId): array
    {
        $base = PassKitTransaction::byAccount($accountId);

        return [
            'total_transactions' => (clone $base)->count(),
            'total_earned' => (int) (clone $base)->where('transaction_type', 'earn')->sum('points_amount'),
            'total_spent' => (int) abs((clone $base)->where('transaction_type', 'burn')->sum('points_amount')),
        ];
    }

    public function batchCreateTransactions(array $transactions): Collection
    {
        return DB::transaction(function () use ($transactions) {
            $created = new Collection();
            foreach ($transactions as $data) {
                $created->push($this->createTransaction($data));
            }
            return $created;
        });
    }

    // Wallet passes
    public function createWalletPass(array $data): WalletPass
    {
        return WalletPass::create($this->withWalletPassDefaults($data));
    }

    public function updateWalletPass(int $id, array $data): ?WalletPass
    {
        $pass = WalletPass::find($id);
        if ($pass === null) {
            return null;
        }
        $pass->update($data);
        return $pass->fresh();
    }

    public function deleteWalletPass(int $id): bool
    {
        $pass = WalletPass::find($id);
        return $pass !== null && $pass->delete();
    }

    public function getWalletPass(int $id): ?WalletPass
    {
        return WalletPass::find($id);
    }

    /**
     * @return Collection<int, WalletPass>|LengthAwarePaginator
     */
    public function listWalletPasses(array $filters = [])
    {
        $query = WalletPass::query();

        foreach (['user_id', 'account_id', 'status', 'program_id', 'template_id', 'member_passkit_id'] as $key) {
            if (isset($filters[$key])) {
                $query->where($key, $filters[$key]);
            }
        }

        if (isset($filters['per_page'])) {
            return $query->paginate((int) $filters['per_page']);
        }

        return $query->get();
    }

    // System helpers
    public function getSystemStats(int $accountId = null): array
    {
        $programs = PassKitProgram::query();
        $members = PassKitMember::query();
        $passes = WalletPass::query();
        $transactions = PassKitTransaction::query();

        if ($accountId !== null) {
            $programs->where('account_id', $accountId);
            $members->where('account_id', $accountId);
            $passes->where('account_id', $accountId);
            $transactions->where('account_id', $accountId);
        }

        return [
            'programs' => $programs->count(),
            'members' => $members->count(),
            'wallet_passes' => $passes->count(),
            'transactions' => $transactions->count(),
        ];
    }

    public function healthCheck(): array
    {
        try {
            DB::connection()->getPdo();
            $dbOk = true;
        } catch (\Throwable $e) {
            $dbOk = false;
        }

        return [
            'database' => $dbOk ? 'ok' : 'error',
            'passkit' => $this->passKitService ? 'configured' : 'missing',
            'timestamp' => now()->toISOString(),
        ];
    }

    protected function validateMemberData(array $data): void
    {
        if (isset($data['email']) && filter_var($data['email'], FILTER_VALIDATE_EMAIL) === false) {
            throw new \InvalidArgumentException('Member email must be a valid email address.');
        }

        if (isset($data['points_balance']) && !is_numeric($data['points_balance'])) {
            throw new \InvalidArgumentException('Member points_balance must be numeric.');
        }
    }

    protected function withProgramDefaults(array $data): array
    {
        return array_merge([
            'passkit_id' => $data['passkit_id'] ?? 'program_' . uniqid(),
            'status' => 'active',
        ], $data);
    }

    protected function withMemberDefaults(array $data): array
    {
        return array_merge([
            'passkit_id' => $data['passkit_id'] ?? 'member_' . uniqid(),
            'status' => 'active',
            'points_balance' => 0,
            'enrolled_at' => now(),
            'program_id' => 0,
        ], $data);
    }

    protected function withTransactionDefaults(array $data): array
    {
        return array_merge([
            'passkit_transaction_id' => $data['passkit_transaction_id'] ?? 'txn_' . uniqid(),
            'status' => 'completed',
            'processed_at' => now(),
            'account_id' => 1,
            'points_balance_before' => 0,
            'points_balance_after' => 0,
        ], $data);
    }

    protected function withWalletPassDefaults(array $data): array
    {
        return array_merge([
            'passkit_id' => $data['passkit_id'] ?? 'pass_' . uniqid(),
            'status' => 'active',
            'issued_at' => now(),
        ], $data);
    }
}
