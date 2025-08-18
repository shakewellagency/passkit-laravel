<?php

namespace ShakewellAgency\PassKitLaravel\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PassKitNotification extends Model
{
    use HasFactory;

    protected $table = 'passkit_notifications';

    protected $fillable = [
        'passkit_notification_id',
        'member_passkit_id',
        'member_id',
        'account_id',
        'title',
        'message',
        'notification_type',
        'category',
        'status',
        'scheduled_at',
        'sent_at',
        'delivered_at',
        'failed_at',
        'failure_reason',
        'personalization_data',
        'language',
        'timezone',
        'transaction_id',
        'campaign_id',
        'metadata',
        'opened',
        'opened_at',
        'clicked',
        'clicked_at',
        'click_url',
        'passkit_payload',
        'passkit_response',
        'retry_count',
        'next_retry_at',
        'max_retries',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'failed_at' => 'datetime',
        'opened_at' => 'datetime',
        'clicked_at' => 'datetime',
        'next_retry_at' => 'datetime',
        'personalization_data' => 'array',
        'metadata' => 'array',
        'passkit_payload' => 'array',
        'passkit_response' => 'array',
        'opened' => 'boolean',
        'clicked' => 'boolean',
        'retry_count' => 'integer',
        'max_retries' => 'integer',
    ];

    // Relationships
    public function member(): BelongsTo
    {
        return $this->belongsTo(PassKitMember::class, 'member_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(PassKitTransaction::class, 'transaction_id');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    public function scopeDelivered($query)
    {
        return $query->where('status', 'delivered');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeByType($query, $type)
    {
        return $query->where('notification_type', $type);
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    public function scopeByAccount($query, $accountId)
    {
        return $query->where('account_id', $accountId);
    }

    public function scopeScheduled($query)
    {
        return $query->whereNotNull('scheduled_at')
                    ->where('scheduled_at', '>', now())
                    ->where('status', 'pending');
    }

    public function scopeReadyToSend($query)
    {
        return $query->where('status', 'pending')
                    ->where(function ($q) {
                        $q->whereNull('scheduled_at')
                          ->orWhere('scheduled_at', '<=', now());
                    });
    }

    public function scopeNeedsRetry($query)
    {
        return $query->where('status', 'failed')
                    ->where('retry_count', '<', 'max_retries')
                    ->where('next_retry_at', '<=', now());
    }

    public function scopeByCampaign($query, $campaignId)
    {
        return $query->where('campaign_id', $campaignId);
    }

    // Accessors
    public function getIsScheduledAttribute(): bool
    {
        return $this->scheduled_at && $this->scheduled_at->isFuture();
    }

    public function getCanRetryAttribute(): bool
    {
        return $this->status === 'failed' && 
               $this->retry_count < $this->max_retries &&
               ($this->next_retry_at === null || $this->next_retry_at->isPast());
    }

    public function getEngagementRateAttribute(): ?float
    {
        if ($this->status !== 'delivered') {
            return null;
        }

        $engagements = 0;
        if ($this->opened) $engagements++;
        if ($this->clicked) $engagements++;

        return $engagements > 0 ? ($engagements / 2) * 100 : 0;
    }

    // Methods
    public function markAsSent(): void
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    public function markAsDelivered(): void
    {
        $this->update([
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);
    }

    public function markAsFailed(string $reason = null): void
    {
        $retryAt = null;
        if ($this->retry_count < $this->max_retries) {
            // Exponential backoff: 5 min, 15 min, 45 min
            $delayMinutes = 5 * pow(3, $this->retry_count);
            $retryAt = now()->addMinutes($delayMinutes);
        }

        $this->update([
            'status' => 'failed',
            'failed_at' => now(),
            'failure_reason' => $reason,
            'next_retry_at' => $retryAt,
        ]);
    }

    public function markAsOpened(): void
    {
        if (!$this->opened) {
            $this->update([
                'opened' => true,
                'opened_at' => now(),
            ]);
        }
    }

    public function markAsClicked(string $url = null): void
    {
        $updateData = [
            'clicked' => true,
            'clicked_at' => now(),
        ];

        if ($url) {
            $updateData['click_url'] = $url;
        }

        // Also mark as opened if not already
        if (!$this->opened) {
            $updateData['opened'] = true;
            $updateData['opened_at'] = now();
        }

        $this->update($updateData);
    }

    public function incrementRetry(): void
    {
        $this->increment('retry_count');
    }

    public function personalize(array $data = []): string
    {
        $message = $this->message;
        $personalizationData = array_merge($this->personalization_data ?? [], $data);

        foreach ($personalizationData as $key => $value) {
            $message = str_replace('{{' . $key . '}}', $value, $message);
        }

        return $message;
    }

    public function schedule(\DateTime $scheduledAt): void
    {
        $this->update([
            'scheduled_at' => $scheduledAt,
            'status' => 'pending',
        ]);
    }

    public function cancel(): void
    {
        if (in_array($this->status, ['pending', 'failed'])) {
            $this->update(['status' => 'cancelled']);
        }
    }
}