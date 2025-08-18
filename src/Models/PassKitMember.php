<?php

namespace ShakewellAgency\PassKitLaravel\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PassKitMember extends Model
{
    use HasFactory;

    protected $table = 'passkit_members';

    protected $fillable = [
        'passkit_id',
        'external_id',
        'user_id',
        'account_id',
        'program_id',
        'tier_id',
        'email',
        'first_name',
        'last_name',
        'phone',
        'date_of_birth',
        'gender',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'postal_code',
        'country',
        'points_balance',
        'lifetime_points',
        'points_to_next_tier',
        'tier_progress',
        'status',
        'opt_in_status',
        'email_opt_in',
        'sms_opt_in',
        'push_opt_in',
        'enrolled_at',
        'enrollment_channel',
        'last_activity_at',
        'tier_achieved_at',
        'preferences',
        'custom_fields',
        'tags',
        'passkit_data',
        'last_sync_at',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'enrolled_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'tier_achieved_at' => 'datetime',
        'last_sync_at' => 'datetime',
        'preferences' => 'array',
        'custom_fields' => 'array',
        'tags' => 'array',
        'passkit_data' => 'array',
        'email_opt_in' => 'boolean',
        'sms_opt_in' => 'boolean',
        'push_opt_in' => 'boolean',
        'tier_progress' => 'decimal:2',
    ];

    // Relationships
    public function program(): BelongsTo
    {
        return $this->belongsTo(PassKitProgram::class, 'program_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PassKitTransaction::class, 'member_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(PassKitNotification::class, 'member_id');
    }

    public function walletPasses(): HasMany
    {
        return $this->hasMany(WalletPass::class, 'member_passkit_id', 'passkit_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByAccount($query, $accountId)
    {
        return $query->where('account_id', $accountId);
    }

    public function scopeByProgram($query, $programId)
    {
        return $query->where('program_id', $programId);
    }

    public function scopeOptedIn($query)
    {
        return $query->where('opt_in_status', 'opted_in');
    }

    // Accessors
    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    public function getFormattedPointsAttribute(): string
    {
        return number_format($this->points_balance);
    }

    // Methods
    public function updateActivity(): void
    {
        $this->update(['last_activity_at' => now()]);
    }

    public function addPoints(int $points, string $description = null): PassKitTransaction
    {
        $transaction = $this->transactions()->create([
            'passkit_transaction_id' => null,
            'member_passkit_id' => $this->passkit_id,
            'account_id' => $this->account_id,
            'transaction_type' => 'earn',
            'points_amount' => $points,
            'points_balance_before' => $this->points_balance,
            'points_balance_after' => $this->points_balance + $points,
            'description' => $description,
            'status' => 'completed',
            'processed_at' => now(),
        ]);

        $this->increment('points_balance', $points);
        $this->increment('lifetime_points', $points);
        $this->updateActivity();

        return $transaction;
    }

    public function subtractPoints(int $points, string $description = null): PassKitTransaction
    {
        if ($this->points_balance < $points) {
            throw new \Exception('Insufficient points balance');
        }

        $transaction = $this->transactions()->create([
            'passkit_transaction_id' => null,
            'member_passkit_id' => $this->passkit_id,
            'account_id' => $this->account_id,
            'transaction_type' => 'burn',
            'points_amount' => -$points,
            'points_balance_before' => $this->points_balance,
            'points_balance_after' => $this->points_balance - $points,
            'description' => $description,
            'status' => 'completed',
            'processed_at' => now(),
        ]);

        $this->decrement('points_balance', $points);
        $this->updateActivity();

        return $transaction;
    }
}