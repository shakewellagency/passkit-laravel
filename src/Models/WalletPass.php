<?php

namespace ShakewellAgency\PassKitLaravel\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class WalletPass extends Model
{
    use HasFactory;

    protected $fillable = [
        'passkit_id',
        'member_passkit_id',
        'user_id',
        'account_id',
        'template_id',
        'program_id',
        'pass_data',
        'field_values',
        'barcode_data',
        'status',
        'is_installed',
        'installed_at',
        'install_urls',
        'qr_codes',
        'device_library_identifier',
        'push_token',
        'device_type',
        'device_model',
        'os_version',
        'app_version',
        'issued_at',
        'first_install_at',
        'last_updated_at',
        'expires_at',
        'voided_at',
        'voided_reason',
        'relevant_locations',
        'relevant_beacons',
        'relevant_date',
        'update_count',
        'last_viewed_at',
        'view_count',
        'usage_analytics',
        'sharable',
        'share_count',
        'share_analytics',
        'last_sync_at',
        'sync_metadata',
        'sync_pending',
    ];

    protected $casts = [
        'pass_data' => 'array',
        'field_values' => 'array',
        'barcode_data' => 'array',
        'install_urls' => 'array',
        'qr_codes' => 'array',
        'relevant_locations' => 'array',
        'relevant_beacons' => 'array',
        'usage_analytics' => 'array',
        'share_analytics' => 'array',
        'sync_metadata' => 'array',
        'installed_at' => 'datetime',
        'issued_at' => 'datetime',
        'first_install_at' => 'datetime',
        'last_updated_at' => 'datetime',
        'expires_at' => 'datetime',
        'voided_at' => 'datetime',
        'relevant_date' => 'datetime',
        'last_viewed_at' => 'datetime',
        'last_sync_at' => 'datetime',
        'is_installed' => 'boolean',
        'sharable' => 'boolean',
        'sync_pending' => 'boolean',
        'update_count' => 'integer',
        'view_count' => 'integer',
        'share_count' => 'integer',
    ];

    // Relationships
    public function template(): BelongsTo
    {
        return $this->belongsTo(CardTemplate::class, 'template_id');
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(PassKitProgram::class, 'program_id');
    }

    public function member(): HasOne
    {
        return $this->hasOne(PassKitMember::class, 'passkit_id', 'member_passkit_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeInstalled($query)
    {
        return $query->where('is_installed', true);
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', now());
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByAccount($query, $accountId)
    {
        return $query->where('account_id', $accountId);
    }

    public function scopeByProgram($query, $programId)
    {
        return $query->where('program_id', $programId);
    }

    public function scopeByDeviceType($query, $deviceType)
    {
        return $query->where('device_type', $deviceType);
    }

    public function scopeNeedsSync($query)
    {
        return $query->where('sync_pending', true);
    }

    public function scopeByMember($query, $memberPasskitId)
    {
        return $query->where('member_passkit_id', $memberPasskitId);
    }

    // Accessors
    public function getIsActiveAttribute(): bool
    {
        return $this->status === 'active';
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function getCurrentTierAttribute(): ?string
    {
        return $this->pass_data['tier'] ?? null;
    }

    public function getIsVoidedAttribute(): bool
    {
        return $this->status === 'voided' || $this->voided_at !== null;
    }

    public function getCurrentPointsAttribute(): int
    {
        return $this->pass_data['points'] ?? 0;
    }

    public function getInstallationStatusAttribute(): string
    {
        if ($this->is_installed) {
            return 'installed';
        }
        
        if ($this->install_urls) {
            return 'pending_install';
        }
        
        return 'not_distributed';
    }

    public function getDaysUntilExpiryAttribute(): ?int
    {
        if (!$this->expires_at) {
            return null;
        }

        return (int) round(now()->diffInDays($this->expires_at, false));
    }

    // Methods
    public function activate(): void
    {
        $this->status = 'active';
        $this->save();
    }

    public function deactivate(): void
    {
        $this->status = 'inactive';
        $this->save();
    }

    public function suspend(): void
    {
        $this->status = 'suspended';
        $this->save();
    }

    public function markAsInstalled($deviceTypeOrInfo = [], ?string $deviceModel = null): void
    {
        $updateData = [
            'is_installed' => true,
            'installed_at' => now(),
        ];

        if (!$this->first_install_at) {
            $updateData['first_install_at'] = now();
        }

        if (is_string($deviceTypeOrInfo)) {
            $updateData['device_type'] = $deviceTypeOrInfo;
            if ($deviceModel !== null) {
                $updateData['device_model'] = $deviceModel;
            }
        } else {
            foreach (['device_library_identifier', 'push_token', 'device_type', 'device_model'] as $key) {
                if (isset($deviceTypeOrInfo[$key])) {
                    $updateData[$key] = $deviceTypeOrInfo[$key];
                }
            }
        }

        $this->update($updateData);
    }

    public function markAsViewed(): void
    {
        $this->increment('view_count');
        $this->update(['last_viewed_at' => now()]);
    }

    public function recordView(): void
    {
        $this->markAsViewed();
    }

    public function markAsShared(): void
    {
        $this->increment('share_count');
    }

    public function recordShare(): void
    {
        $this->markAsShared();
    }

    public function incrementUpdateCount(): void
    {
        $this->increment('update_count');
    }

    public function setSyncPending(): void
    {
        $this->update(['sync_pending' => true]);
    }

    public function clearSyncPending(): void
    {
        $this->update([
            'sync_pending' => false,
            'last_sync_at' => now(),
        ]);
    }

    public function updatePassData(array $newData): void
    {
        $currentData = $this->pass_data ?? [];
        $updatedData = array_merge($currentData, $newData);
        
        $this->update([
            'pass_data' => $updatedData,
            'last_updated_at' => now(),
            'sync_pending' => true,
        ]);
        
        $this->increment('update_count');
    }

    public function updatePoints(int $newPoints): void
    {
        $this->updatePassData(['points' => $newPoints]);
    }

    public function void(string $reason = null): void
    {
        $this->update([
            'status' => 'voided',
            'voided_at' => now(),
            'voided_reason' => $reason,
            'sync_pending' => true,
        ]);
    }

    public function markSyncCompleted(): void
    {
        $this->update([
            'sync_pending' => false,
            'last_sync_at' => now(),
        ]);
    }

    public function generateInstallUrls(): array
    {
        // This would integrate with PassKit API to generate installation URLs
        // For now, returning a placeholder structure
        $urls = [
            'apple' => "https://wallet.passkit.com/install/{$this->passkit_id}",
            'google' => "https://pay.google.com/save/{$this->passkit_id}",
        ];

        $this->update(['install_urls' => $urls]);
        
        return $urls;
    }

    public function trackAnalytics(string $event, array $data = []): void
    {
        $analytics = $this->usage_analytics ?? [];
        
        if (!isset($analytics[$event])) {
            $analytics[$event] = [];
        }
        
        $analytics[$event][] = array_merge($data, [
            'timestamp' => now()->toISOString(),
        ]);
        
        $this->update(['usage_analytics' => $analytics]);
    }
}