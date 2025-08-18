<?php

namespace ShakewellAgency\PassKitLaravel\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use ShakewellAgency\PassKitLaravel\Services\PassKitService;
use ShakewellAgency\PassKitLaravel\Services\PassKitCrudManager;
use ShakewellAgency\PassKitLaravel\Models\PassKitProgram;
use ShakewellAgency\PassKitLaravel\Models\PassKitMember;
use ShakewellAgency\PassKitLaravel\Models\PassKitTransaction;
use ShakewellAgency\PassKitLaravel\Models\WalletPass;
use ShakewellAgency\PassKitLaravel\Http\Requests\Api\CreateProgramRequest;
use ShakewellAgency\PassKitLaravel\Http\Requests\Api\CreateMemberRequest;
use ShakewellAgency\PassKitLaravel\Http\Requests\Api\UpdateMemberRequest;
use ShakewellAgency\PassKitLaravel\Http\Requests\Api\CreateTransactionRequest;

/**
 * Shakewell Wallet REST API Controller
 * 
 * Provides comprehensive REST API endpoints for Shakewell Wallet integration
 * All endpoints support JSON input/output with proper error handling
 */
class PassKitApiController
{
    protected PassKitService $passKitService;
    protected PassKitCrudManager $crudManager;

    public function __construct(
        PassKitService $passKitService,
        PassKitCrudManager $crudManager
    ) {
        $this->passKitService = $passKitService;
        $this->crudManager = $crudManager;
    }

    /**
     * @api {get} /api/wallet/programs Get all loyalty programs
     * @apiName GetPrograms
     * @apiGroup Programs
     * @apiVersion 1.0.0
     * 
     * @apiParam {Number} [account_id] Filter by account ID
     * @apiParam {String} [status=active] Filter by status (active, inactive, draft)
     * @apiParam {Number} [page=1] Page number for pagination
     * @apiParam {Number} [per_page=15] Items per page (max 100)
     * 
     * @apiSuccess {Boolean} success Request success status
     * @apiSuccess {Object[]} data Array of loyalty programs
     * @apiSuccess {Object} meta Pagination metadata
     */
    public function getPrograms(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'account_id' => 'nullable|integer|exists:accounts,id',
                'status' => 'nullable|in:active,inactive,draft',
                'page' => 'nullable|integer|min:1',
                'per_page' => 'nullable|integer|min:1|max:100'
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', $validator->errors(), 422);
            }

            $query = PassKitProgram::query();

            if ($request->filled('account_id')) {
                $query->where('account_id', $request->account_id);
            }

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            $programs = $query->paginate($request->per_page ?? 15);

            return $this->successResponse([
                'programs' => $programs->items(),
                'meta' => [
                    'current_page' => $programs->currentPage(),
                    'total_pages' => $programs->lastPage(),
                    'total_count' => $programs->total(),
                    'per_page' => $programs->perPage(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Shakewell Wallet API: Failed to get programs', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->errorResponse('Failed to retrieve loyalty programs', [], 500);
        }
    }

    /**
     * @api {post} /api/wallet/programs Create loyalty program
     * @apiName CreateProgram
     * @apiGroup Programs
     * @apiVersion 1.0.0
     */
    public function createProgram(CreateProgramRequest $request): JsonResponse
    {
        try {
            $program = $this->crudManager->createProgram($request->validated());

            return $this->successResponse([
                'program' => $program,
                'message' => 'Program created successfully'
            ], 201);

        } catch (\Exception $e) {
            Log::error('API: Failed to create program', [
                'error' => $e->getMessage(),
                'data' => $request->validated()
            ]);

            return $this->errorResponse('Failed to create program', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @api {get} /api/passkit/programs/{id} Get program
     * @apiName GetProgram
     * @apiGroup Programs
     * @apiVersion 1.0.0
     */
    public function getProgram(PassKitProgram $program): JsonResponse
    {
        try {
            $program->load(['tiers', 'members']);

            return $this->successResponse([
                'program' => $program,
                'statistics' => [
                    'total_members' => $program->members()->count(),
                    'active_members' => $program->members()->active()->count(),
                    'total_transactions' => $program->transactions()->count(),
                    'total_points_issued' => $program->transactions()->sum('points_amount')
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('API: Failed to get program', [
                'program_id' => $program->id,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Failed to retrieve program', [], 500);
        }
    }

    /**
     * @api {put} /api/passkit/programs/{id} Update program
     * @apiName UpdateProgram
     * @apiGroup Programs
     * @apiVersion 1.0.0
     */
    public function updateProgram(CreateProgramRequest $request, PassKitProgram $program): JsonResponse
    {
        try {
            $updatedProgram = $this->crudManager->updateProgram($program->id, $request->validated());

            return $this->successResponse([
                'program' => $updatedProgram,
                'message' => 'Program updated successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('API: Failed to update program', [
                'program_id' => $program->id,
                'error' => $e->getMessage(),
                'data' => $request->validated()
            ]);

            return $this->errorResponse('Failed to update program', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @api {delete} /api/passkit/programs/{id} Delete program
     * @apiName DeleteProgram
     * @apiGroup Programs
     * @apiVersion 1.0.0
     */
    public function deleteProgram(PassKitProgram $program): JsonResponse
    {
        try {
            $this->crudManager->deleteProgram($program->id);

            return $this->successResponse([
                'message' => 'Program deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('API: Failed to delete program', [
                'program_id' => $program->id,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Failed to delete program', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @api {get} /api/passkit/members Get members
     * @apiName GetMembers
     * @apiGroup Members
     * @apiVersion 1.0.0
     */
    public function getMembers(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'account_id' => 'nullable|integer|exists:accounts,id',
                'program_id' => 'nullable|integer|exists:passkit_programs,id',
                'status' => 'nullable|in:active,inactive,suspended',
                'email' => 'nullable|email',
                'external_id' => 'nullable|string',
                'page' => 'nullable|integer|min:1',
                'per_page' => 'nullable|integer|min:1|max:100'
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', $validator->errors(), 422);
            }

            $query = PassKitMember::with(['program', 'transactions']);

            if ($request->filled('account_id')) {
                $query->where('account_id', $request->account_id);
            }

            if ($request->filled('program_id')) {
                $query->where('program_id', $request->program_id);
            }

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('email')) {
                $query->where('email', 'like', '%' . $request->email . '%');
            }

            if ($request->filled('external_id')) {
                $query->where('external_id', $request->external_id);
            }

            $members = $query->paginate($request->per_page ?? 15);

            return $this->successResponse([
                'members' => $members->items(),
                'meta' => [
                    'current_page' => $members->currentPage(),
                    'total_pages' => $members->lastPage(),
                    'total_count' => $members->total(),
                    'per_page' => $members->perPage(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('API: Failed to get members', [
                'error' => $e->getMessage(),
                'filters' => $request->all()
            ]);

            return $this->errorResponse('Failed to retrieve members', [], 500);
        }
    }

    /**
     * @api {post} /api/passkit/members Create member
     * @apiName CreateMember
     * @apiGroup Members
     * @apiVersion 1.0.0
     */
    public function createMember(CreateMemberRequest $request): JsonResponse
    {
        try {
            // Create member in local database
            $member = $this->crudManager->createMember($request->validated());

            // Enroll in PassKit API if sync is enabled
            if (config('passkit.auto_sync', true)) {
                try {
                    $passkitResult = $this->passKitService->enrollMember(
                        $member->tier_id,
                        [
                            'externalId' => $member->external_id,
                            'firstName' => $member->first_name,
                            'lastName' => $member->last_name,
                            'email' => $member->email,
                            'points' => $member->points_balance
                        ]
                    );

                    // Update with PassKit ID
                    $member->update([
                        'passkit_id' => $passkitResult['id'],
                        'last_sync_at' => now(),
                        'sync_pending' => false
                    ]);

                } catch (\Exception $syncError) {
                    Log::warning('API: Member created locally but PassKit sync failed', [
                        'member_id' => $member->id,
                        'sync_error' => $syncError->getMessage()
                    ]);

                    $member->update(['sync_pending' => true]);
                }
            }

            return $this->successResponse([
                'member' => $member->fresh(),
                'message' => 'Member created successfully'
            ], 201);

        } catch (\Exception $e) {
            Log::error('API: Failed to create member', [
                'error' => $e->getMessage(),
                'data' => $request->validated()
            ]);

            return $this->errorResponse('Failed to create member', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @api {get} /api/passkit/members/{id} Get member
     * @apiName GetMember
     * @apiGroup Members
     * @apiVersion 1.0.0
     */
    public function getMember(PassKitMember $member): JsonResponse
    {
        try {
            $member->load(['program', 'transactions', 'walletPasses']);

            return $this->successResponse([
                'member' => $member,
                'statistics' => [
                    'lifetime_points' => $member->lifetime_points,
                    'current_balance' => $member->points_balance,
                    'total_transactions' => $member->transactions()->count(),
                    'last_activity' => $member->last_activity_at,
                    'wallet_passes_count' => $member->walletPasses()->count()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('API: Failed to get member', [
                'member_id' => $member->id,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Failed to retrieve member', [], 500);
        }
    }

    /**
     * @api {put} /api/passkit/members/{id} Update member
     * @apiName UpdateMember
     * @apiGroup Members
     * @apiVersion 1.0.0
     */
    public function updateMember(UpdateMemberRequest $request, PassKitMember $member): JsonResponse
    {
        try {
            $updatedMember = $this->crudManager->updateMember($member->id, $request->validated());

            return $this->successResponse([
                'member' => $updatedMember,
                'message' => 'Member updated successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('API: Failed to update member', [
                'member_id' => $member->id,
                'error' => $e->getMessage(),
                'data' => $request->validated()
            ]);

            return $this->errorResponse('Failed to update member', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @api {post} /api/passkit/members/{id}/points Update member points
     * @apiName UpdateMemberPoints
     * @apiGroup Members
     * @apiVersion 1.0.0
     */
    public function updateMemberPoints(Request $request, PassKitMember $member): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'points' => 'required|integer',
                'description' => 'nullable|string|max:255',
                'reference_id' => 'nullable|string|max:100'
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', $validator->errors(), 422);
            }

            // Update points via service
            $result = $this->passKitService->updateMemberPoints(
                $member->passkit_id,
                $request->points,
                $request->description ?? 'Points update via API'
            );

            // Create local transaction record
            $transaction = $this->crudManager->createTransaction([
                'member_id' => $member->id,
                'member_passkit_id' => $member->passkit_id,
                'transaction_type' => $request->points > 0 ? 'earn' : 'burn',
                'points_amount' => $request->points,
                'points_balance_before' => $member->points_balance,
                'points_balance_after' => $member->points_balance + $request->points,
                'description' => $request->description ?? 'Points update via API',
                'reference_id' => $request->reference_id,
                'status' => 'completed',
                'processed_at' => now()
            ]);

            // Update member balance
            $member->update([
                'points_balance' => $member->points_balance + $request->points,
                'last_activity_at' => now(),
                'last_sync_at' => now()
            ]);

            return $this->successResponse([
                'member' => $member->fresh(),
                'transaction' => $transaction,
                'message' => 'Points updated successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('API: Failed to update member points', [
                'member_id' => $member->id,
                'points' => $request->points,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Failed to update points', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @api {post} /api/passkit/transactions Create transaction
     * @apiName CreateTransaction
     * @apiGroup Transactions
     * @apiVersion 1.0.0
     */
    public function createTransaction(CreateTransactionRequest $request): JsonResponse
    {
        try {
            $transaction = $this->crudManager->createTransaction($request->validated());

            return $this->successResponse([
                'transaction' => $transaction,
                'message' => 'Transaction created successfully'
            ], 201);

        } catch (\Exception $e) {
            Log::error('API: Failed to create transaction', [
                'error' => $e->getMessage(),
                'data' => $request->validated()
            ]);

            return $this->errorResponse('Failed to create transaction', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @api {get} /api/passkit/transactions Get transactions
     * @apiName GetTransactions
     * @apiGroup Transactions
     * @apiVersion 1.0.0
     */
    public function getTransactions(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'member_id' => 'nullable|integer|exists:passkit_members,id',
                'transaction_type' => 'nullable|in:earn,burn,expire,transfer',
                'status' => 'nullable|in:pending,completed,failed,cancelled',
                'date_from' => 'nullable|date',
                'date_to' => 'nullable|date|after_or_equal:date_from',
                'page' => 'nullable|integer|min:1',
                'per_page' => 'nullable|integer|min:1|max:100'
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', $validator->errors(), 422);
            }

            $query = PassKitTransaction::with(['member']);

            if ($request->filled('member_id')) {
                $query->where('member_id', $request->member_id);
            }

            if ($request->filled('transaction_type')) {
                $query->where('transaction_type', $request->transaction_type);
            }

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('date_from')) {
                $query->whereDate('processed_at', '>=', $request->date_from);
            }

            if ($request->filled('date_to')) {
                $query->whereDate('processed_at', '<=', $request->date_to);
            }

            $transactions = $query->orderBy('processed_at', 'desc')
                                 ->paginate($request->per_page ?? 15);

            return $this->successResponse([
                'transactions' => $transactions->items(),
                'meta' => [
                    'current_page' => $transactions->currentPage(),
                    'total_pages' => $transactions->lastPage(),
                    'total_count' => $transactions->total(),
                    'per_page' => $transactions->perPage(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('API: Failed to get transactions', [
                'error' => $e->getMessage(),
                'filters' => $request->all()
            ]);

            return $this->errorResponse('Failed to retrieve transactions', [], 500);
        }
    }

    /**
     * @api {get} /api/passkit/wallet-passes Get wallet passes
     * @apiName GetWalletPasses
     * @apiGroup WalletPasses
     * @apiVersion 1.0.0
     */
    public function getWalletPasses(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'user_id' => 'nullable|integer|exists:users,id',
                'account_id' => 'nullable|integer|exists:accounts,id',
                'status' => 'nullable|in:active,expired,voided',
                'is_installed' => 'nullable|boolean',
                'page' => 'nullable|integer|min:1',
                'per_page' => 'nullable|integer|min:1|max:100'
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', $validator->errors(), 422);
            }

            $query = WalletPass::with(['user', 'account']);

            if ($request->filled('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            if ($request->filled('account_id')) {
                $query->where('account_id', $request->account_id);
            }

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('is_installed')) {
                $query->where('is_installed', $request->boolean('is_installed'));
            }

            $passes = $query->paginate($request->per_page ?? 15);

            return $this->successResponse([
                'wallet_passes' => $passes->items(),
                'meta' => [
                    'current_page' => $passes->currentPage(),
                    'total_pages' => $passes->lastPage(),
                    'total_count' => $passes->total(),
                    'per_page' => $passes->perPage(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('API: Failed to get wallet passes', [
                'error' => $e->getMessage(),
                'filters' => $request->all()
            ]);

            return $this->errorResponse('Failed to retrieve wallet passes', [], 500);
        }
    }

    /**
     * @api {post} /api/passkit/wallet-passes Create wallet pass
     * @apiName CreateWalletPass
     * @apiGroup WalletPasses
     * @apiVersion 1.0.0
     */
    public function createWalletPass(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'member_passkit_id' => 'required|string|exists:passkit_members,passkit_id',
                'user_id' => 'required|integer|exists:users,id',
                'account_id' => 'required|integer|exists:accounts,id',
                'pass_type' => 'nullable|string|in:loyalty,membership,coupon,event',
                'pass_data' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', $validator->errors(), 422);
            }

            $member = PassKitMember::where('passkit_id', $request->member_passkit_id)->first();

            if (!$member) {
                return $this->errorResponse('Member not found', [], 404);
            }

            // Create wallet pass with installation URLs
            $walletPass = $this->passKitService->createWalletPassWithUrls(
                $request->member_passkit_id,
                $request->user_id,
                $request->account_id,
                $request->pass_type ?? 'loyalty',
                $request->pass_data ?? []
            );

            return $this->successResponse([
                'wallet_pass' => $walletPass,
                'installation_urls' => $walletPass->install_urls,
                'message' => 'Wallet pass created successfully'
            ], 201);

        } catch (\Exception $e) {
            Log::error('API: Failed to create wallet pass', [
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);

            return $this->errorResponse('Failed to create wallet pass', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @api {get} /api/passkit/sync/status Get sync status
     * @apiName GetSyncStatus
     * @apiGroup Sync
     * @apiVersion 1.0.0
     */
    public function getSyncStatus(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'account_id' => 'nullable|integer|exists:accounts,id'
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', $validator->errors(), 422);
            }

            $accountId = $request->account_id;

            $pendingMembers = PassKitMember::when($accountId, function($query) use ($accountId) {
                return $query->where('account_id', $accountId);
            })->where('sync_pending', true)->count();

            $pendingPasses = WalletPass::when($accountId, function($query) use ($accountId) {
                return $query->where('account_id', $accountId);
            })->where('sync_pending', true)->count();

            $lastSync = PassKitMember::when($accountId, function($query) use ($accountId) {
                return $query->where('account_id', $accountId);
            })->whereNotNull('last_sync_at')->max('last_sync_at');

            return $this->successResponse([
                'sync_status' => [
                    'pending_members' => $pendingMembers,
                    'pending_wallet_passes' => $pendingPasses,
                    'last_sync_at' => $lastSync,
                    'sync_healthy' => $pendingMembers === 0 && $pendingPasses === 0
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('API: Failed to get sync status', [
                'error' => $e->getMessage(),
                'account_id' => $request->account_id
            ]);

            return $this->errorResponse('Failed to get sync status', [], 500);
        }
    }

    /**
     * @api {post} /api/passkit/sync/trigger Trigger sync
     * @apiName TriggerSync
     * @apiGroup Sync
     * @apiVersion 1.0.0
     */
    public function triggerSync(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'account_id' => 'required|integer|exists:accounts,id',
                'entity_type' => 'nullable|in:members,wallet_passes,all',
                'force' => 'nullable|boolean'
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', $validator->errors(), 422);
            }

            $accountId = $request->account_id;
            $entityType = $request->entity_type ?? 'all';
            $force = $request->boolean('force', false);

            $results = [];

            if (in_array($entityType, ['members', 'all'])) {
                $memberResults = $this->passKitService->syncAllMembers($accountId, [
                    'force' => $force,
                    'batch_size' => 50
                ]);
                $results['members'] = $memberResults;
            }

            if (in_array($entityType, ['wallet_passes', 'all'])) {
                $passResults = $this->passKitService->syncAllWalletPasses($accountId, [
                    'force' => $force,
                    'batch_size' => 50
                ]);
                $results['wallet_passes'] = $passResults;
            }

            return $this->successResponse([
                'sync_results' => $results,
                'message' => 'Sync completed successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('API: Failed to trigger sync', [
                'error' => $e->getMessage(),
                'account_id' => $request->account_id,
                'entity_type' => $request->entity_type
            ]);

            return $this->errorResponse('Failed to trigger sync', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @api {get} /api/passkit/analytics Get analytics
     * @apiName GetAnalytics
     * @apiGroup Analytics
     * @apiVersion 1.0.0
     */
    public function getAnalytics(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'account_id' => 'required|integer|exists:accounts,id',
                'date_from' => 'nullable|date',
                'date_to' => 'nullable|date|after_or_equal:date_from',
                'metrics' => 'nullable|array',
                'metrics.*' => 'in:members,transactions,wallet_passes,points'
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', $validator->errors(), 422);
            }

            $accountId = $request->account_id;
            $dateFrom = $request->date_from ?? now()->subDays(30)->toDateString();
            $dateTo = $request->date_to ?? now()->toDateString();
            $metrics = $request->metrics ?? ['members', 'transactions', 'wallet_passes', 'points'];

            $analytics = [];

            if (in_array('members', $metrics)) {
                $analytics['members'] = [
                    'total' => PassKitMember::where('account_id', $accountId)->count(),
                    'active' => PassKitMember::where('account_id', $accountId)->active()->count(),
                    'new_this_period' => PassKitMember::where('account_id', $accountId)
                        ->whereBetween('enrolled_at', [$dateFrom, $dateTo])
                        ->count()
                ];
            }

            if (in_array('transactions', $metrics)) {
                $analytics['transactions'] = [
                    'total' => PassKitTransaction::whereHas('member', function($query) use ($accountId) {
                        $query->where('account_id', $accountId);
                    })->count(),
                    'this_period' => PassKitTransaction::whereHas('member', function($query) use ($accountId) {
                        $query->where('account_id', $accountId);
                    })->whereBetween('processed_at', [$dateFrom, $dateTo])->count(),
                    'earn_transactions' => PassKitTransaction::whereHas('member', function($query) use ($accountId) {
                        $query->where('account_id', $accountId);
                    })->where('transaction_type', 'earn')->count(),
                    'burn_transactions' => PassKitTransaction::whereHas('member', function($query) use ($accountId) {
                        $query->where('account_id', $accountId);
                    })->where('transaction_type', 'burn')->count()
                ];
            }

            if (in_array('wallet_passes', $metrics)) {
                $analytics['wallet_passes'] = [
                    'total' => WalletPass::where('account_id', $accountId)->count(),
                    'installed' => WalletPass::where('account_id', $accountId)->where('is_installed', true)->count(),
                    'active' => WalletPass::where('account_id', $accountId)->where('status', 'active')->count()
                ];
            }

            if (in_array('points', $metrics)) {
                $analytics['points'] = [
                    'total_issued' => PassKitTransaction::whereHas('member', function($query) use ($accountId) {
                        $query->where('account_id', $accountId);
                    })->where('transaction_type', 'earn')->sum('points_amount'),
                    'total_redeemed' => PassKitTransaction::whereHas('member', function($query) use ($accountId) {
                        $query->where('account_id', $accountId);
                    })->where('transaction_type', 'burn')->sum('points_amount'),
                    'outstanding_balance' => PassKitMember::where('account_id', $accountId)->sum('points_balance')
                ];
            }

            return $this->successResponse([
                'analytics' => $analytics,
                'period' => [
                    'from' => $dateFrom,
                    'to' => $dateTo
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('API: Failed to get analytics', [
                'error' => $e->getMessage(),
                'account_id' => $request->account_id
            ]);

            return $this->errorResponse('Failed to get analytics', [], 500);
        }
    }

    /**
     * Standard success response format
     */
    protected function successResponse(array $data = [], int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data,
            'timestamp' => now()->toISOString()
        ], $status);
    }

    /**
     * Standard error response format
     */
    protected function errorResponse(string $message, array $errors = [], int $status = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
            'timestamp' => now()->toISOString()
        ], $status);
    }
}