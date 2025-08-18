<?php

namespace ShakewellAgency\PassKitLaravel\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PassKitTier extends Model
{
    protected $fillable = [
        'account_id',
        'program_id',
        'passkit_id',
        'name',
        'tier_index',
        'timezone',
        'template_id',
        'upgrade_message',
        'downgrade_message',
        'metadata',
        'created_by',
        'last_synced_at',
        'sync_error'
    ];

    protected $casts = [
        'tier_index' => 'integer',
        'metadata' => 'array',
        'last_synced_at' => 'datetime'
    ];

    /**
     * Get the account this tier belongs to.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Get the program this tier belongs to.
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(PassKitProgram::class, 'program_id', 'passkit_id');
    }

    /**
     * Get the template for this tier.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(CardTemplate::class, 'template_id', 'passkit_template_id');
    }

    /**
     * Get the user who created this tier.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the wallet passes for this tier.
     */
    public function walletPasses(): HasMany
    {
        return $this->hasMany(WalletPass::class, 'passkit_tier_id', 'passkit_id');
    }

    /**
     * Scope for tiers by account.
     */
    public function scopeForAccount($query, $accountId)
    {
        return $query->where('account_id', $accountId);
    }

    /**
     * Scope for tiers by program.
     */
    public function scopeForProgram($query, string $programId)
    {
        return $query->where('program_id', $programId);
    }

    /**
     * Scope for tiers ordered by index.
     */
    public function scopeByIndex($query)
    {
        return $query->orderBy('tier_index');
    }

    /**
     * Get sync status.
     */
    public function getSyncStatus(): string
    {
        if ($this->sync_error) {
            return 'error';
        }

        if ($this->last_synced_at) {
            return 'synced';
        }

        return 'not_synced';
    }

    /**
     * Mark sync as successful.
     */
    public function markSyncSuccessful(array $metadata = []): void
    {
        $this->update([
            'last_synced_at' => now(),
            'sync_error' => null,
            'metadata' => array_merge($this->metadata ?? [], $metadata)
        ]);
    }

    /**
     * Mark sync as failed.
     */
    public function markSyncFailed(string $error): void
    {
        $this->update([
            'sync_error' => $error,
            'last_synced_at' => now()
        ]);
    }

    /**
     * Check if tier has template.
     */
    public function hasTemplate(): bool
    {
        return !is_null($this->template_id);
    }

    /**
     * Get tier display name.
     */
    public function getDisplayName(): string
    {
        return $this->name ?? "Tier {$this->tier_index}";
    }
}