<?php

namespace ShakewellAgency\PassKitLaravel\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PassKitTransaction extends Model
{
    use HasFactory;

    protected $table = 'passkit_transactions';

    protected $fillable = [
        'passkit_transaction_id',
        'member_passkit_id',
        'member_id',
        'account_id',
        'transaction_type',
        'points_amount',
        'points_balance_before',
        'points_balance_after',
        'description',
        'reference_id',
        'source',
        'category',
        'location_id',
        'location_name',
        'latitude',
        'longitude',
        'purchase_amount',
        'currency',
        'points_multiplier',
        'expires_at',
        'is_expired',
        'expired_at',
        'status',
        'failure_reason',
        'processed_at',
        'webhook_data',
        'passkit_data',
        'created_by',
        'reversed_by_transaction_id',
        'reversal_transaction_id',
    ];

    protected $casts = [
        'points_amount' => 'integer',
        'points_balance_before' => 'integer',
        'points_balance_after' => 'integer',
        'purchase_amount' => 'decimal:2',
        'points_multiplier' => 'decimal:2',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'expires_at' => 'datetime',
        'expired_at' => 'datetime',
        'processed_at' => 'datetime',
        'is_expired' => 'boolean',
        'webhook_data' => 'array',
        'passkit_data' => 'array',
    ];

    // Relationships
    public function member(): BelongsTo
    {
        return $this->belongsTo(PassKitMember::class, 'member_id');
    }

    public function reversalTransaction(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_transaction_id');
    }

    public function reversedByTransaction(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversed_by_transaction_id');
    }

    // Scopes
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeByType($query, $type)
    {
        return $query->where('transaction_type', $type);
    }

    public function scopeByAccount($query, $accountId)
    {
        return $query->where('account_id', $accountId);
    }

    public function scopeByMember($query, $memberId)
    {
        return $query->where('member_id', $memberId);
    }

    public function scopeEarned($query)
    {
        return $query->where('transaction_type', 'earn')->where('points_amount', '>', 0);
    }

    public function scopeBurned($query)
    {
        return $query->where('transaction_type', 'burn')->where('points_amount', '<', 0);
    }

    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('processed_at', [$startDate, $endDate]);
    }

    public function scopeByLocation($query, $locationId)
    {
        return $query->where('location_id', $locationId);
    }

    public function scopeBySource($query, $source)
    {
        return $query->where('source', $source);
    }

    // Accessors
    public function getFormattedAmountAttribute(): string
    {
        $sign = $this->points_amount >= 0 ? '+' : '';
        return $sign . number_format($this->points_amount);
    }

    public function getIsEarnAttribute(): bool
    {
        return $this->transaction_type === 'earn' && $this->points_amount > 0;
    }

    public function getIsBurnAttribute(): bool
    {
        return $this->transaction_type === 'burn' && $this->points_amount < 0;
    }

    public function getFormattedPurchaseAmountAttribute(): ?string
    {
        if (!$this->purchase_amount) {
            return null;
        }
        
        return ($this->currency ?? 'USD') . ' ' . number_format($this->purchase_amount, 2);
    }

    // Methods
    public function reverse(string $reason = null): self
    {
        if ($this->status !== 'completed') {
            throw new \Exception('Only completed transactions can be reversed');
        }

        if ($this->reversal_transaction_id) {
            throw new \Exception('Transaction has already been reversed');
        }

        $reversalTransaction = self::create([
            'member_passkit_id' => $this->member_passkit_id,
            'member_id' => $this->member_id,
            'account_id' => $this->account_id,
            'transaction_type' => $this->transaction_type === 'earn' ? 'burn' : 'earn',
            'points_amount' => -$this->points_amount,
            'points_balance_before' => $this->member->points_balance,
            'points_balance_after' => $this->member->points_balance - $this->points_amount,
            'description' => 'Reversal: ' . ($reason ?? $this->description),
            'reference_id' => $this->reference_id,
            'source' => $this->source,
            'category' => 'reversal',
            'status' => 'completed',
            'processed_at' => now(),
            'reversed_by_transaction_id' => $this->id,
        ]);

        $this->update(['reversal_transaction_id' => $reversalTransaction->id]);

        // Update member points balance
        $this->member->decrement('points_balance', $this->points_amount);

        return $reversalTransaction;
    }

    public function markExpired(): void
    {
        if ($this->transaction_type === 'earn' && !$this->is_expired && $this->expires_at && $this->expires_at->isPast()) {
            $this->update([
                'is_expired' => true,
                'expired_at' => now(),
            ]);

            // Create expiry transaction
            if ($this->points_amount > 0) {
                self::create([
                    'member_passkit_id' => $this->member_passkit_id,
                    'member_id' => $this->member_id,
                    'account_id' => $this->account_id,
                    'transaction_type' => 'expire',
                    'points_amount' => -$this->points_amount,
                    'points_balance_before' => $this->member->points_balance,
                    'points_balance_after' => $this->member->points_balance - $this->points_amount,
                    'description' => 'Points expired from transaction: ' . $this->id,
                    'reference_id' => 'expire_' . $this->id,
                    'category' => 'expiry',
                    'status' => 'completed',
                    'processed_at' => now(),
                ]);

                $this->member->decrement('points_balance', $this->points_amount);
            }
        }
    }
}