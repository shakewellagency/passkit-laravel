<?php

namespace ShakewellAgency\PassKitLaravel\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use ShakewellAgency\PassKitLaravel\Services\PassKitCrudManager;
use Illuminate\Support\Facades\Validator;

class PassKitApiController extends Controller
{
    protected PassKitCrudManager $crudManager;

    public function __construct(PassKitCrudManager $crudManager)
    {
        $this->crudManager = $crudManager;
    }

    public function health(): JsonResponse
    {
        $health = $this->crudManager->healthCheck();
        return response()->json($health, $health['overall'] ? 200 : 503);
    }

    // Programs
    public function getPrograms(Request $request): JsonResponse
    {
        $accountId = $request->get('account_id');
        $type = $request->get('type');
        
        if (!$accountId) {
            return response()->json(['error' => 'Account ID required'], 400);
        }

        $programs = $this->crudManager->getProgramsByAccount($accountId, $type);
        return response()->json(['programs' => $programs]);
    }

    public function createProgram(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:membership,event_ticket,coupon',
            'account_id' => 'required|integer',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $result = $this->crudManager->createProgram(
                $request->type,
                $request->only(['name', 'description']),
                $request->account_id
            );
            return response()->json($result, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getProgram(int $id): JsonResponse
    {
        $program = $this->crudManager->getProgram($id);
        if (!$program) {
            return response()->json(['error' => 'Program not found'], 404);
        }
        return response()->json(['program' => $program]);
    }

    public function updateProgram(Request $request, int $id): JsonResponse
    {
        try {
            $result = $this->crudManager->updateProgram($id, $request->all());
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function deleteProgram(int $id): JsonResponse
    {
        try {
            $success = $this->crudManager->deleteProgram($id);
            return response()->json(['success' => $success]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // Members
    public function createMember(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'tier_id' => 'required|string',
            'user_id' => 'required|integer',
            'account_id' => 'required|integer',
            'external_id' => 'required|string',
            'points' => 'integer|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $result = $this->crudManager->createMember(
                $request->tier_id,
                $request->except(['tier_id', 'user_id', 'account_id']),
                $request->user_id,
                $request->account_id
            );
            return response()->json($result, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getMember(string $id): JsonResponse
    {
        $member = $this->crudManager->getMember($id);
        if (!$member) {
            return response()->json(['error' => 'Member not found'], 404);
        }
        return response()->json(['member' => $member]);
    }

    public function updateMemberPoints(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'points' => 'required|integer',
            'description' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $result = $this->crudManager->updateMemberPoints(
                $id,
                $request->points,
                $request->description
            );
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getInstallationPackage(string $id): JsonResponse
    {
        try {
            $package = $this->crudManager->getInstallationPackage($id);
            return response()->json(['package' => $package]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function sendNotification(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'message' => 'required|string',
            'points' => 'integer|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $success = $this->crudManager->sendNotification(
                $id,
                $request->message,
                $request->get('points', 0)
            );
            return response()->json(['success' => $success]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getSystemStats(): JsonResponse
    {
        $stats = $this->crudManager->getSystemStats();
        return response()->json(['stats' => $stats]);
    }

    // Additional CRUD methods for tiers, templates, wallet passes
    public function getTiersByProgram(int $programId): JsonResponse
    {
        $tiers = $this->crudManager->getTiersByProgram($programId);
        return response()->json(['tiers' => $tiers]);
    }

    public function getWalletPassesByUser(int $userId): JsonResponse
    {
        $passes = $this->crudManager->getWalletPassesByUser($userId);
        return response()->json(['wallet_passes' => $passes]);
    }

    public function getWalletPassesByAccount(int $accountId): JsonResponse
    {
        $passes = $this->crudManager->getWalletPassesByAccount($accountId);
        return response()->json(['wallet_passes' => $passes]);
    }
}