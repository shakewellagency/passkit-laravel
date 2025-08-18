<?php

namespace ShakewellAgency\PassKitLaravel\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PassKitWebhook extends Model
{
    use HasFactory;

    protected $table = 'passkit_webhooks';

    protected $fillable = [
        'webhook_id',
        'event_type',
        'event_id',
        'source_ip',
        'user_agent',
        'headers',
        'payload',
        'signature',
        'status',
        'processed_at',
        'error_message',
        'retry_count',
        'next_retry_at',
        'member_passkit_id',
        'member_id',
        'account_id',
        'extracted_data',
        'transaction_id',
        'notification_id',
        'signature_valid',
        'duplicate_event',
        'idempotency_key',
        'processor_version',
        'processing_metadata',
        'processing_time_ms',
    ];

    protected $casts = [
        'processed_at' => 'datetime',
        'next_retry_at' => 'datetime',
        'headers' => 'array',
        'extracted_data' => 'array',
        'processing_metadata' => 'array',
        'signature_valid' => 'boolean',
        'duplicate_event' => 'boolean',
        'retry_count' => 'integer',
        'processing_time_ms' => 'decimal:3',
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

    public function notification(): BelongsTo
    {
        return $this->belongsTo(PassKitNotification::class, 'notification_id');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    public function scopeProcessed($query)
    {
        return $query->where('status', 'processed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeByEventType($query, $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    public function scopeByAccount($query, $accountId)
    {
        return $query->where('account_id', $accountId);
    }

    public function scopeNeedsRetry($query)
    {
        return $query->where('status', 'failed')
                    ->where('retry_count', '<', 3)
                    ->where('next_retry_at', '<=', now());
    }

    public function scopeValidSignature($query)
    {
        return $query->where('signature_valid', true);
    }

    public function scopeUnprocessed($query)
    {
        return $query->whereIn('status', ['pending', 'failed'])
                    ->where('duplicate_event', false);
    }

    // Accessors
    public function getPayloadDataAttribute(): ?array
    {
        return json_decode($this->payload, true);
    }

    public function getCanRetryAttribute(): bool
    {
        return $this->status === 'failed' && 
               $this->retry_count < 3 &&
               ($this->next_retry_at === null || $this->next_retry_at->isPast());
    }

    public function getProcessingDurationAttribute(): ?string
    {
        if (!$this->processing_time_ms) {
            return null;
        }

        if ($this->processing_time_ms < 1000) {
            return number_format($this->processing_time_ms, 1) . 'ms';
        }

        return number_format($this->processing_time_ms / 1000, 2) . 's';
    }

    // Methods
    public function markAsProcessing(): void
    {
        $this->update([
            'status' => 'processing',
            'processed_at' => now(),
        ]);
    }

    public function markAsProcessed(array $extractedData = null, float $processingTimeMs = null): void
    {
        $updateData = [
            'status' => 'processed',
            'processed_at' => now(),
        ];

        if ($extractedData !== null) {
            $updateData['extracted_data'] = $extractedData;
        }

        if ($processingTimeMs !== null) {
            $updateData['processing_time_ms'] = $processingTimeMs;
        }

        $this->update($updateData);
    }

    public function markAsFailed(string $errorMessage, float $processingTimeMs = null): void
    {
        $retryAt = null;
        if ($this->retry_count < 3) {
            // Exponential backoff: 1 min, 5 min, 30 min
            $delayMinutes = [1, 5, 30][$this->retry_count] ?? 30;
            $retryAt = now()->addMinutes($delayMinutes);
        }

        $updateData = [
            'status' => 'failed',
            'error_message' => $errorMessage,
            'next_retry_at' => $retryAt,
        ];

        if ($processingTimeMs !== null) {
            $updateData['processing_time_ms'] = $processingTimeMs;
        }

        $this->update($updateData);
    }

    public function validateSignature(string $secret): bool
    {
        if (!$this->signature || !$this->payload) {
            return false;
        }

        $expectedSignature = hash_hmac('sha256', $this->payload, $secret);
        $providedSignature = str_replace('sha256=', '', $this->signature);

        $isValid = hash_equals($expectedSignature, $providedSignature);

        $this->update(['signature_valid' => $isValid]);

        return $isValid;
    }

    public function extractData(): array
    {
        $payloadData = $this->payload_data;
        
        if (!$payloadData) {
            return [];
        }

        $extracted = [
            'event_type' => $this->event_type,
            'timestamp' => $payloadData['timestamp'] ?? now()->toISOString(),
        ];

        // Extract member information
        if (isset($payloadData['member'])) {
            $extracted['member'] = [
                'passkit_id' => $payloadData['member']['id'] ?? null,
                'external_id' => $payloadData['member']['externalId'] ?? null,
                'email' => $payloadData['member']['person']['emailAddress'] ?? null,
                'points' => $payloadData['member']['points'] ?? null,
            ];
        }

        // Extract transaction information
        if (isset($payloadData['transaction'])) {
            $extracted['transaction'] = [
                'id' => $payloadData['transaction']['id'] ?? null,
                'type' => $payloadData['transaction']['type'] ?? null,
                'amount' => $payloadData['transaction']['amount'] ?? null,
                'description' => $payloadData['transaction']['description'] ?? null,
            ];
        }

        // Extract pass information
        if (isset($payloadData['pass'])) {
            $extracted['pass'] = [
                'id' => $payloadData['pass']['id'] ?? null,
                'status' => $payloadData['pass']['status'] ?? null,
                'installed' => $payloadData['pass']['installed'] ?? null,
            ];
        }

        return $extracted;
    }

    public function incrementRetry(): void
    {
        $this->increment('retry_count');
    }

    public function markAsDuplicate(string $originalWebhookId = null): void
    {
        $this->update([
            'duplicate_event' => true,
            'status' => 'ignored',
            'processing_metadata' => array_merge($this->processing_metadata ?? [], [
                'duplicate_of' => $originalWebhookId,
                'marked_duplicate_at' => now()->toISOString(),
            ]),
        ]);
    }

    public function setProcessingMetadata(array $metadata): void
    {
        $this->update([
            'processing_metadata' => array_merge($this->processing_metadata ?? [], $metadata),
        ]);
    }
}