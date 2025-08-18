<?php

namespace ShakewellAgency\PassKitLaravel\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PassKitProgram extends Model
{
    protected $fillable = [
        'account_id',
        'passkit_id',
        'name',
        'description',
        'status',
        'program_type',
        'metadata',
        'created_by',
        'last_synced_at',
        'sync_error'
    ];

    protected $casts = [
        'status' => 'array',
        'metadata' => 'array',
        'last_synced_at' => 'datetime'
    ];

    /**
     * Get the account this program belongs to.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Get the user who created this program.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the tiers for this program.
     */
    public function tiers(): HasMany
    {
        return $this->hasMany(PassKitTier::class, 'program_id', 'passkit_id');
    }

    /**
     * Get the templates for this program.
     */
    public function templates(): HasMany
    {
        return $this->hasMany(CardTemplate::class, 'passkit_program_id', 'passkit_id');
    }

    /**
     * Scope for active programs.
     */
    public function scopeActive($query)
    {
        return $query->whereJsonContains('status', 'PROJECT_ACTIVE_FOR_OBJECT_CREATION');
    }

    /**
     * Scope for programs by type.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('program_type', $type);
    }

    /**
     * Scope for programs by account.
     */
    public function scopeForAccount($query, $accountId)
    {
        return $query->where('account_id', $accountId);
    }

    /**
     * Check if program is active.
     */
    public function isActive(): bool
    {
        return in_array('PROJECT_ACTIVE_FOR_OBJECT_CREATION', $this->status ?? []);
    }

    /**
     * Check if program is published.
     */
    public function isPublished(): bool
    {
        return in_array('PROJECT_PUBLISHED', $this->status ?? []);
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
}