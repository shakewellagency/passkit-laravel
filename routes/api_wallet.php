<?php

use Illuminate\Support\Facades\Route;
use ShakewellAgency\PassKitLaravel\Http\Controllers\Api\WalletApiController;

/*
|--------------------------------------------------------------------------
| Shakewell Wallet API Routes
|--------------------------------------------------------------------------
|
| Comprehensive REST API endpoints for Shakewell Wallet integration
| All routes are prefixed with 'api/wallet' and require API authentication
|
*/

Route::prefix('api/wallet')->group(function () {
    
    // Program Management Routes
    Route::prefix('programs')->group(function () {
        Route::get('/', [WalletApiController::class, 'getPrograms'])
            ->name('api.wallet.programs.index');
        
        Route::post('/', [WalletApiController::class, 'createProgram'])
            ->name('api.wallet.programs.store');
        
        Route::get('{program}', [WalletApiController::class, 'getProgram'])
            ->name('api.wallet.programs.show');
        
        Route::put('{program}', [WalletApiController::class, 'updateProgram'])
            ->name('api.wallet.programs.update');
        
        Route::delete('{program}', [WalletApiController::class, 'deleteProgram'])
            ->name('api.wallet.programs.destroy');
    });
    
    // Member Management Routes
    Route::prefix('members')->group(function () {
        Route::get('/', [WalletApiController::class, 'getMembers'])
            ->name('api.wallet.members.index');
        
        Route::post('/', [WalletApiController::class, 'createMember'])
            ->name('api.wallet.members.store');
        
        Route::get('{member}', [WalletApiController::class, 'getMember'])
            ->name('api.wallet.members.show');
        
        Route::put('{member}', [WalletApiController::class, 'updateMember'])
            ->name('api.wallet.members.update');
        
        Route::post('{member}/points', [WalletApiController::class, 'updateMemberPoints'])
            ->name('api.wallet.members.points.update');
    });
    
    // Transaction Management Routes
    Route::prefix('transactions')->group(function () {
        Route::get('/', [WalletApiController::class, 'getTransactions'])
            ->name('api.wallet.transactions.index');
        
        Route::post('/', [WalletApiController::class, 'createTransaction'])
            ->name('api.wallet.transactions.store');
    });
    
    // Wallet Pass Management Routes
    Route::prefix('passes')->group(function () {
        Route::get('/', [WalletApiController::class, 'getWalletPasses'])
            ->name('api.wallet.passes.index');
        
        Route::post('/', [WalletApiController::class, 'createWalletPass'])
            ->name('api.wallet.passes.store');
    });
    
    // Synchronization Routes
    Route::prefix('sync')->group(function () {
        Route::get('status', [WalletApiController::class, 'getSyncStatus'])
            ->name('api.wallet.sync.status');
        
        Route::post('trigger', [WalletApiController::class, 'triggerSync'])
            ->name('api.wallet.sync.trigger');
    });
    
    // Analytics Routes
    Route::get('analytics', [WalletApiController::class, 'getAnalytics'])
        ->name('api.wallet.analytics');
});

/*
|--------------------------------------------------------------------------
| API Documentation Routes
|--------------------------------------------------------------------------
|
| Routes for serving API documentation and examples
|
*/

Route::prefix('api/wallet/docs')->group(function () {
    Route::get('/', function () {
        return view('shakewell-wallet::api.docs.index');
    })->name('api.wallet.docs.index');
    
    Route::get('programs', function () {
        return view('shakewell-wallet::api.docs.programs');
    })->name('api.wallet.docs.programs');
    
    Route::get('members', function () {
        return view('shakewell-wallet::api.docs.members');
    })->name('api.wallet.docs.members');
    
    Route::get('transactions', function () {
        return view('shakewell-wallet::api.docs.transactions');
    })->name('api.wallet.docs.transactions');
    
    Route::get('passes', function () {
        return view('shakewell-wallet::api.docs.passes');
    })->name('api.wallet.docs.passes');
    
    Route::get('authentication', function () {
        return view('shakewell-wallet::api.docs.authentication');
    })->name('api.wallet.docs.authentication');
    
    Route::get('examples', function () {
        return view('shakewell-wallet::api.docs.examples');
    })->name('api.wallet.docs.examples');
});

/*
|--------------------------------------------------------------------------
| API Testing Routes
|--------------------------------------------------------------------------
|
| Routes for testing API functionality (available in non-production environments)
|
*/

if (app()->environment(['local', 'staging', 'testing'])) {
    Route::prefix('api/wallet/test')->group(function () {
        Route::get('connection', function () {
            return response()->json([
                'success' => true,
                'message' => 'Shakewell Wallet API is operational',
                'timestamp' => now()->toISOString(),
                'environment' => app()->environment(),
                'version' => '1.0.0'
            ]);
        })->name('api.wallet.test.connection');
        
        Route::post('sample-data', function () {
            // Create sample data for testing
            $program = \ShakewellAgency\PassKitLaravel\Models\PassKitProgram::create([
                'passkit_id' => 'test_program_' . uniqid(),
                'name' => 'Test Loyalty Program',
                'description' => 'Sample loyalty program for API testing',
                'program_type' => 'loyalty',
                'account_id' => 1,
                'status' => 'active',
                'settings' => [
                    'points_per_dollar' => 1,
                    'welcome_points' => 100,
                    'minimum_redemption' => 500
                ]
            ]);
            
            $member = \ShakewellAgency\PassKitLaravel\Models\PassKitMember::create([
                'passkit_id' => 'test_member_' . uniqid(),
                'external_id' => 'api_test_user_' . uniqid(),
                'program_id' => $program->id,
                'tier_id' => 'bronze',
                'account_id' => 1,
                'email' => 'test@shakewellwallet.com',
                'first_name' => 'Test',
                'last_name' => 'User',
                'points_balance' => 250,
                'status' => 'active',
                'enrolled_at' => now()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Sample data created successfully',
                'data' => [
                    'program' => $program,
                    'member' => $member
                ]
            ], 201);
        })->name('api.wallet.test.sample-data');
    });
}