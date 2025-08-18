<?php

use Illuminate\Support\Facades\Route;
use ShakewellAgency\PassKitLaravel\Http\Controllers\PassKitApiController;

Route::prefix('api/passkit')->middleware(['api'])->group(function () {
    
    // Health check
    Route::get('health', [PassKitApiController::class, 'health']);
    
    // Programs
    Route::prefix('programs')->group(function () {
        Route::get('/', [PassKitApiController::class, 'getPrograms']);
        Route::post('/', [PassKitApiController::class, 'createProgram']);
        Route::get('{id}', [PassKitApiController::class, 'getProgram']);
        Route::put('{id}', [PassKitApiController::class, 'updateProgram']);
        Route::delete('{id}', [PassKitApiController::class, 'deleteProgram']);
    });

    // Tiers
    Route::prefix('tiers')->group(function () {
        Route::get('program/{programId}', [PassKitApiController::class, 'getTiersByProgram']);
        Route::post('program/{programId}', [PassKitApiController::class, 'createTier']);
        Route::get('{id}', [PassKitApiController::class, 'getTier']);
        Route::put('{id}', [PassKitApiController::class, 'updateTier']);
        Route::delete('{id}', [PassKitApiController::class, 'deleteTier']);
    });

    // Members
    Route::prefix('members')->group(function () {
        Route::post('/', [PassKitApiController::class, 'createMember']);
        Route::get('{id}', [PassKitApiController::class, 'getMember']);
        Route::put('{id}/points', [PassKitApiController::class, 'updateMemberPoints']);
        Route::delete('{id}', [PassKitApiController::class, 'deleteMember']);
        Route::get('{id}/installation', [PassKitApiController::class, 'getInstallationPackage']);
        Route::post('{id}/notification', [PassKitApiController::class, 'sendNotification']);
    });

    // Wallet Passes
    Route::prefix('wallet-passes')->group(function () {
        Route::get('user/{userId}', [PassKitApiController::class, 'getWalletPassesByUser']);
        Route::get('account/{accountId}', [PassKitApiController::class, 'getWalletPassesByAccount']);
        Route::get('{id}', [PassKitApiController::class, 'getWalletPass']);
    });

    // Templates
    Route::prefix('templates')->group(function () {
        Route::get('account/{accountId}', [PassKitApiController::class, 'getTemplatesByAccount']);
        Route::post('/', [PassKitApiController::class, 'createTemplate']);
        Route::get('{id}', [PassKitApiController::class, 'getTemplate']);
        Route::put('{id}', [PassKitApiController::class, 'updateTemplate']);
        Route::delete('{id}', [PassKitApiController::class, 'deleteTemplate']);
    });

    // System Statistics
    Route::get('stats', [PassKitApiController::class, 'getSystemStats']);
});