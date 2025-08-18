<?php

namespace ShakewellAgency\PassKitLaravel\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shakewell Wallet API Authentication Middleware
 * 
 * Handles API authentication using Bearer tokens with rate limiting
 */
class WalletApiAuth
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check for API key in header
        $apiKey = $request->bearerToken() ?? $request->header('X-API-Key');
        
        if (!$apiKey) {
            return $this->unauthorizedResponse('API key required');
        }

        // Validate API key
        $account = $this->validateApiKey($apiKey);
        
        if (!$account) {
            return $this->unauthorizedResponse('Invalid API key');
        }

        // Check rate limiting
        $rateLimitResult = $this->checkRateLimit($apiKey, $request);
        
        if (!$rateLimitResult['allowed']) {
            return $this->rateLimitResponse($rateLimitResult);
        }

        // Set authenticated account in request
        $request->merge(['authenticated_account' => $account]);
        $request->merge(['api_key' => $apiKey]);

        // Log API usage
        $this->logApiUsage($request, $account);

        return $next($request);
    }

    /**
     * Validate API key and return associated account
     */
    protected function validateApiKey(string $apiKey): ?object
    {
        // Check cache first for performance
        $cacheKey = "wallet_api_key:{$apiKey}";
        $account = Cache::remember($cacheKey, 300, function () use ($apiKey) {
            // In a real implementation, this would check against a database table
            // For now, we'll simulate with config-based validation
            $validKeys = config('passkit.api_keys', []);
            
            foreach ($validKeys as $keyData) {
                if (hash_equals($keyData['key'], $apiKey)) {
                    return (object) [
                        'id' => $keyData['account_id'],
                        'name' => $keyData['name'] ?? 'API Account',
                        'permissions' => $keyData['permissions'] ?? ['read', 'write'],
                        'rate_limit' => $keyData['rate_limit'] ?? 1000, // requests per hour
                        'active' => $keyData['active'] ?? true
                    ];
                }
            }
            
            return null;
        });

        if (!$account || !$account->active) {
            return null;
        }

        return $account;
    }

    /**
     * Check rate limiting for API key
     */
    protected function checkRateLimit(string $apiKey, Request $request): array
    {
        $account = $request->get('authenticated_account');
        $rateLimit = $account->rate_limit ?? 1000;
        
        // Create rate limit key
        $rateLimitKey = "wallet_api_rate_limit:{$apiKey}:" . now()->format('Y-m-d-H');
        
        // Get current usage
        $currentUsage = Cache::get($rateLimitKey, 0);
        
        if ($currentUsage >= $rateLimit) {
            return [
                'allowed' => false,
                'limit' => $rateLimit,
                'remaining' => 0,
                'reset_time' => now()->addHour()->startOfHour()
            ];
        }

        // Increment usage
        Cache::put($rateLimitKey, $currentUsage + 1, 3600); // 1 hour TTL

        return [
            'allowed' => true,
            'limit' => $rateLimit,
            'remaining' => $rateLimit - ($currentUsage + 1),
            'reset_time' => now()->addHour()->startOfHour()
        ];
    }

    /**
     * Log API usage for monitoring and analytics
     */
    protected function logApiUsage(Request $request, object $account): void
    {
        try {
            Log::channel('wallet_api')->info('Shakewell Wallet API Request', [
                'account_id' => $account->id,
                'account_name' => $account->name,
                'method' => $request->method(),
                'endpoint' => $request->path(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'timestamp' => now()->toISOString(),
                'request_id' => $request->header('X-Request-ID') ?? uniqid()
            ]);
        } catch (\Exception $e) {
            // Silent fail - don't break API for logging issues
            Log::error('Failed to log API usage', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Return unauthorized response
     */
    protected function unauthorizedResponse(string $message): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'error_code' => 'UNAUTHORIZED',
            'timestamp' => now()->toISOString()
        ], 401);
    }

    /**
     * Return rate limit exceeded response
     */
    protected function rateLimitResponse(array $rateLimitData): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Rate limit exceeded',
            'error_code' => 'RATE_LIMIT_EXCEEDED',
            'rate_limit' => [
                'limit' => $rateLimitData['limit'],
                'remaining' => 0,
                'reset_time' => $rateLimitData['reset_time']->toISOString()
            ],
            'timestamp' => now()->toISOString()
        ], 429)->header('Retry-After', $rateLimitData['reset_time']->diffInSeconds(now()));
    }
}