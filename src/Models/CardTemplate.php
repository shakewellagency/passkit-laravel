<?php

namespace ShakewellAgency\PassKitLaravel\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CardTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_id',
        'name',
        'description',
        'template_data',
        'field_definitions',
        'is_active',
        'created_by',
        'passkit_template_id',
        'passkit_program_id',
        'passkit_tier_id',
        'passkit_metadata',
        'passkit_synced_at',
        'passkit_sync_error',
        'passkit_enabled',
    ];

    protected function casts(): array
    {
        return [
            'template_data' => 'array',
            'field_definitions' => 'array',
            'is_active' => 'boolean',
            'passkit_metadata' => 'array',
            'passkit_synced_at' => 'datetime',
            'passkit_enabled' => 'boolean',
        ];
    }

    /**
     * Get the user who created this template.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the cards using this template.
     */
    public function cards(): HasMany
    {
        return $this->hasMany(Card::class, 'template_id');
    }

    /**
     * Scope for active templates.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for templates by creator.
     */
    public function scopeCreatedBy($query, $userId)
    {
        return $query->where('created_by', $userId);
    }

    /**
     * Get the account this template belongs to.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Scope for templates by account.
     */
    public function scopeForAccount($query, $accountId)
    {
        return $query->where('account_id', $accountId);
    }

    /**
     * Scope for PassKit-enabled templates.
     */
    public function scopePassKitEnabled($query)
    {
        return $query->where('passkit_enabled', true);
    }

    /**
     * Scope for templates synced with PassKit.
     */
    public function scopePassKitSynced($query)
    {
        return $query->whereNotNull('passkit_template_id')
                     ->whereNull('passkit_sync_error');
    }

    /**
     * Scope for templates with PassKit sync errors.
     */
    public function scopePassKitSyncErrors($query)
    {
        return $query->whereNotNull('passkit_sync_error');
    }

    /**
     * Check if template is synced with PassKit.
     */
    public function isPassKitSynced(): bool
    {
        return !is_null($this->passkit_template_id) && is_null($this->passkit_sync_error);
    }

    /**
     * Check if template has PassKit sync errors.
     */
    public function hasPassKitSyncErrors(): bool
    {
        return !is_null($this->passkit_sync_error);
    }

    /**
     * Get PassKit sync status.
     */
    public function getPassKitSyncStatus(): string
    {
        if (!$this->passkit_enabled) {
            return 'disabled';
        }

        if ($this->hasPassKitSyncErrors()) {
            return 'error';
        }

        if ($this->isPassKitSynced()) {
            return 'synced';
        }

        if (!is_null($this->passkit_template_id)) {
            return 'partial';
        }

        return 'not_synced';
    }

    /**
     * Clear PassKit sync errors.
     */
    public function clearPassKitSyncErrors(): void
    {
        $this->update([
            'passkit_sync_error' => null,
            'passkit_synced_at' => now()
        ]);
    }

    /**
     * Mark PassKit sync as successful.
     */
    public function markPassKitSyncSuccessful(string $templateId, array $metadata = []): void
    {
        $this->update([
            'passkit_template_id' => $templateId,
            'passkit_synced_at' => now(),
            'passkit_sync_error' => null,
            'passkit_metadata' => array_merge($this->passkit_metadata ?? [], $metadata)
        ]);
    }

    /**
     * Mark PassKit sync as failed.
     */
    public function markPassKitSyncFailed(string $error): void
    {
        $this->update([
            'passkit_sync_error' => $error,
            'passkit_synced_at' => now()
        ]);
    }

    /**
     * Get PassKit metadata value.
     */
    public function getPassKitMetadata(string $key, $default = null)
    {
        return data_get($this->passkit_metadata, $key, $default);
    }

    /**
     * Set PassKit metadata value.
     */
    public function setPassKitMetadata(string $key, $value): void
    {
        $metadata = $this->passkit_metadata ?? [];
        data_set($metadata, $key, $value);
        $this->update(['passkit_metadata' => $metadata]);
    }
}
