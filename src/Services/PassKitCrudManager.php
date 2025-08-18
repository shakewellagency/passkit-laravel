<?php

namespace ShakewellAgency\PassKitLaravel\Services;

use ShakewellAgency\PassKitLaravel\Models\PassKitProgram;
use ShakewellAgency\PassKitLaravel\Models\PassKitTier;
use ShakewellAgency\PassKitLaravel\Models\CardTemplate;
use ShakewellAgency\PassKitLaravel\Models\WalletPass;
use App\Models\User;
use App\Models\Account;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Collection;

/**
 * PassKit CRUD Management Service
 * Comprehensive service container for all PassKit operations
 * 
 * @author Claude Code with PassKit Integration
 * @version 1.0.0
 */
class PassKitCrudManager
{
    protected PassKitService $passKitService;

    public function __construct(PassKitService $passKitService)
    {
        $this->passKitService = $passKitService;
    }

    // ==========================================
    // PROGRAM MANAGEMENT (CREATE, READ, UPDATE, DELETE)
    // ==========================================

    /**
     * Create a new PassKit program
     */
    public function createProgram(string $type, array $data, int $accountId): array
    {
        try {
            DB::beginTransaction();

            $result = match($type) {
                'membership' => $this->passKitService->createMembershipProgram($data),
                'event_ticket' => $this->passKitService->createEventTicketProgram($data),
                'coupon' => $this->passKitService->createCouponProgram($data),
                default => throw new \InvalidArgumentException("Invalid program type: {$type}")
            };

            if (!$result['success']) {
                throw new \Exception('Program creation failed');
            }

            // Update database record with account association
            $program = PassKitProgram::where('passkit_id', $result['id'])->first();
            if ($program) {
                $program->update(['account_id' => $accountId]);
            }

            DB::commit();

            Log::info('PassKit program created via CRUD manager', [
                'type' => $type,
                'passkit_id' => $result['id'],
                'account_id' => $accountId
            ]);

            return [
                'success' => true,
                'program' => $program,
                'passkit_result' => $result
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('PassKit program creation failed', [
                'type' => $type,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get program by ID
     */
    public function getProgram(int $programId): ?PassKitProgram
    {
        return PassKitProgram::with(['tiers', 'account'])->find($programId);
    }

    /**
     * Get programs by account
     */
    public function getProgramsByAccount(int $accountId, string $type = null): Collection
    {
        $query = PassKitProgram::where('account_id', $accountId)->with(['tiers']);
        
        if ($type) {
            $query->where('program_type', $type);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Update program
     */
    public function updateProgram(int $programId, array $data): array
    {
        try {
            $program = $this->getProgram($programId);
            if (!$program) {
                throw new \Exception("Program not found: {$programId}");
            }

            // Update local record
            $program->update([
                'name' => $data['name'] ?? $program->name,
                'description' => $data['description'] ?? $program->description,
                'status' => $data['status'] ?? $program->status,
                'metadata' => array_merge($program->metadata ?? [], $data['metadata'] ?? [])
            ]);

            Log::info('PassKit program updated', [
                'program_id' => $programId,
                'passkit_id' => $program->passkit_id
            ]);

            return [
                'success' => true,
                'program' => $program->fresh()
            ];

        } catch (\Exception $e) {
            Log::error('PassKit program update failed', [
                'program_id' => $programId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Delete program
     */
    public function deleteProgram(int $programId): bool
    {
        try {
            $program = $this->getProgram($programId);
            if (!$program) {
                throw new \Exception("Program not found: {$programId}");
            }

            // Soft delete or mark as inactive
            $program->update(['status' => 'inactive']);

            Log::info('PassKit program deleted', [
                'program_id' => $programId,
                'passkit_id' => $program->passkit_id
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('PassKit program deletion failed', [
                'program_id' => $programId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    // ==========================================
    // TIER MANAGEMENT (CREATE, READ, UPDATE, DELETE)
    // ==========================================

    /**
     * Create a new tier
     */
    public function createTier(int $programId, array $data): array
    {
        try {
            $program = $this->getProgram($programId);
            if (!$program) {
                throw new \Exception("Program not found: {$programId}");
            }

            $result = $this->passKitService->createMembershipTier(array_merge($data, [
                'program_id' => $program->passkit_id
            ]));

            Log::info('PassKit tier created via CRUD manager', [
                'program_id' => $programId,
                'tier_id' => $result['id']
            ]);

            return [
                'success' => true,
                'tier' => PassKitTier::where('passkit_id', $result['id'])->first(),
                'passkit_result' => $result
            ];

        } catch (\Exception $e) {
            Log::error('PassKit tier creation failed', [
                'program_id' => $programId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get tier by ID
     */
    public function getTier(int $tierId): ?PassKitTier
    {
        return PassKitTier::with(['program'])->find($tierId);
    }

    /**
     * Get tiers by program
     */
    public function getTiersByProgram(int $programId): Collection
    {
        return PassKitTier::whereHas('program', function($query) use ($programId) {
            $query->where('id', $programId);
        })->orderBy('created_at', 'desc')->get();
    }

    /**
     * Update tier
     */
    public function updateTier(int $tierId, array $data): array
    {
        try {
            $tier = $this->getTier($tierId);
            if (!$tier) {
                throw new \Exception("Tier not found: {$tierId}");
            }

            $tier->update([
                'name' => $data['name'] ?? $tier->name,
                'description' => $data['description'] ?? $tier->description,
                'template_id' => $data['template_id'] ?? $tier->template_id,
                'metadata' => array_merge($tier->metadata ?? [], $data['metadata'] ?? [])
            ]);

            return [
                'success' => true,
                'tier' => $tier->fresh()
            ];

        } catch (\Exception $e) {
            Log::error('PassKit tier update failed', [
                'tier_id' => $tierId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Delete tier
     */
    public function deleteTier(int $tierId): bool
    {
        try {
            $tier = $this->getTier($tierId);
            if (!$tier) {
                throw new \Exception("Tier not found: {$tierId}");
            }

            $tier->delete();

            Log::info('PassKit tier deleted', [
                'tier_id' => $tierId,
                'passkit_id' => $tier->passkit_id
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('PassKit tier deletion failed', [
                'tier_id' => $tierId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    // ==========================================
    // MEMBER MANAGEMENT (CREATE, READ, UPDATE, DELETE)
    // ==========================================

    /**
     * Create/Enroll a new member
     */
    public function createMember(string $tierId, array $memberData, int $userId, int $accountId): array
    {
        try {
            $result = $this->passKitService->enrollMemberWithWalletPass(
                $tierId,
                $memberData,
                $userId,
                $accountId
            );

            Log::info('PassKit member created via CRUD manager', [
                'member_id' => $result['member_id'],
                'user_id' => $userId,
                'account_id' => $accountId
            ]);

            return $result;

        } catch (\Exception $e) {
            Log::error('PassKit member creation failed', [
                'tier_id' => $tierId,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get member by PassKit ID
     */
    public function getMember(string $memberId): ?array
    {
        try {
            return $this->passKitService->getMember($memberId);
        } catch (\Exception $e) {
            Log::error('PassKit member retrieval failed', [
                'member_id' => $memberId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get wallet pass by member ID
     */
    public function getWalletPass(string $memberId): ?WalletPass
    {
        return WalletPass::where('passkit_id', $memberId)->with(['user', 'account'])->first();
    }

    /**
     * Get wallet passes by user
     */
    public function getWalletPassesByUser(int $userId): Collection
    {
        return WalletPass::where('user_id', $userId)->with(['account'])->orderBy('created_at', 'desc')->get();
    }

    /**
     * Get wallet passes by account
     */
    public function getWalletPassesByAccount(int $accountId): Collection
    {
        return WalletPass::where('account_id', $accountId)->with(['user'])->orderBy('created_at', 'desc')->get();
    }

    /**
     * Update member points
     */
    public function updateMemberPoints(string $memberId, int $points, string $description = null): array
    {
        try {
            $result = $this->passKitService->updateMemberPoints($memberId, $points, $description);

            // Update local wallet pass
            $walletPass = $this->getWalletPass($memberId);
            if ($walletPass) {
                $currentData = $walletPass->pass_data;
                $currentData['points'] = ($currentData['points'] ?? 0) + $points;
                $walletPass->update(['pass_data' => $currentData]);
            }

            Log::info('PassKit member points updated via CRUD manager', [
                'member_id' => $memberId,
                'points_added' => $points
            ]);

            return [
                'success' => true,
                'member_id' => $memberId,
                'points_added' => $points,
                'wallet_pass' => $walletPass
            ];

        } catch (\Exception $e) {
            Log::error('PassKit member points update failed', [
                'member_id' => $memberId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Delete member
     */
    public function deleteMember(string $memberId): bool
    {
        try {
            $result = $this->passKitService->deleteMember($memberId);

            // Update local wallet pass
            $walletPass = $this->getWalletPass($memberId);
            if ($walletPass) {
                $walletPass->update(['status' => 'inactive']);
            }

            Log::info('PassKit member deleted via CRUD manager', [
                'member_id' => $memberId
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('PassKit member deletion failed', [
                'member_id' => $memberId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    // ==========================================
    // TEMPLATE MANAGEMENT (CREATE, READ, UPDATE, DELETE)
    // ==========================================

    /**
     * Create template
     */
    public function createTemplate(string $type, string $tierId, array $data): array
    {
        try {
            $result = match($type) {
                'membership' => $this->passKitService->createPassTemplate($tierId, $data),
                'event_ticket' => $this->passKitService->createEventTicketTemplate($tierId, $data),
                'coupon' => $this->passKitService->createCouponTemplate($tierId, $data),
                default => throw new \InvalidArgumentException("Invalid template type: {$type}")
            };

            Log::info('PassKit template created via CRUD manager', [
                'type' => $type,
                'tier_id' => $tierId,
                'template_id' => $result['id'] ?? 'unknown'
            ]);

            return [
                'success' => true,
                'template' => CardTemplate::where('passkit_template_id', $result['id'] ?? null)->first(),
                'passkit_result' => $result
            ];

        } catch (\Exception $e) {
            Log::error('PassKit template creation failed', [
                'type' => $type,
                'tier_id' => $tierId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get template by ID
     */
    public function getTemplate(int $templateId): ?CardTemplate
    {
        return CardTemplate::with(['account'])->find($templateId);
    }

    /**
     * Get templates by account
     */
    public function getTemplatesByAccount(int $accountId): Collection
    {
        return CardTemplate::where('account_id', $accountId)
            ->whereNotNull('passkit_template_id')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Update template
     */
    public function updateTemplate(int $templateId, array $data): array
    {
        try {
            $template = $this->getTemplate($templateId);
            if (!$template) {
                throw new \Exception("Template not found: {$templateId}");
            }

            $template->update([
                'name' => $data['name'] ?? $template->name,
                'description' => $data['description'] ?? $template->description,
                'template_data' => array_merge($template->template_data ?? [], $data['template_data'] ?? []),
                'field_definitions' => array_merge($template->field_definitions ?? [], $data['field_definitions'] ?? [])
            ]);

            return [
                'success' => true,
                'template' => $template->fresh()
            ];

        } catch (\Exception $e) {
            Log::error('PassKit template update failed', [
                'template_id' => $templateId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Delete template
     */
    public function deleteTemplate(int $templateId): bool
    {
        try {
            $template = $this->getTemplate($templateId);
            if (!$template) {
                throw new \Exception("Template not found: {$templateId}");
            }

            $template->update(['is_active' => false]);

            Log::info('PassKit template deleted', [
                'template_id' => $templateId,
                'passkit_template_id' => $template->passkit_template_id
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('PassKit template deletion failed', [
                'template_id' => $templateId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    // ==========================================
    // UTILITY METHODS
    // ==========================================

    /**
     * Get installation package for member
     */
    public function getInstallationPackage(string $memberId): array
    {
        try {
            return $this->passKitService->getPassInstallationPackage($memberId);
        } catch (\Exception $e) {
            Log::error('Installation package retrieval failed', [
                'member_id' => $memberId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Send notification to member
     */
    public function sendNotification(string $memberId, string $message, int $points = 0): bool
    {
        try {
            return $this->passKitService->sendPushNotification($memberId, $message, $points);
        } catch (\Exception $e) {
            Log::error('Notification sending failed', [
                'member_id' => $memberId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get system statistics
     */
    public function getSystemStats(): array
    {
        return [
            'programs' => [
                'total' => PassKitProgram::count(),
                'by_type' => PassKitProgram::selectRaw('program_type, COUNT(*) as count')
                    ->groupBy('program_type')
                    ->pluck('count', 'program_type')
                    ->toArray()
            ],
            'tiers' => PassKitTier::count(),
            'templates' => CardTemplate::whereNotNull('passkit_template_id')->count(),
            'wallet_passes' => [
                'total' => WalletPass::count(),
                'active' => WalletPass::where('status', 'active')->count(),
                'installed' => WalletPass::where('is_installed', true)->count()
            ],
            'total_points' => WalletPass::sum(DB::raw("JSON_EXTRACT(pass_data, '$.points')")) ?? 0
        ];
    }

    /**
     * Health check
     */
    public function healthCheck(): array
    {
        try {
            $connection = $this->passKitService->testConnection();
            $dbCheck = DB::table('pass_kit_programs')->count() >= 0;

            return [
                'passkit_api' => $connection,
                'database' => $dbCheck,
                'overall' => $connection && $dbCheck
            ];

        } catch (\Exception $e) {
            return [
                'passkit_api' => false,
                'database' => false,
                'overall' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}