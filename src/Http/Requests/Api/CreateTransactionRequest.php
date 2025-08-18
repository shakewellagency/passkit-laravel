<?php

namespace ShakewellAgency\PassKitLaravel\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class CreateTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    public function rules(): array
    {
        return [
            'member_id' => 'required|integer|exists:passkit_members,id',
            'member_passkit_id' => 'required|string|exists:passkit_members,passkit_id',
            'account_id' => 'required|integer|exists:accounts,id',
            'transaction_type' => 'required|in:earn,burn,expire,transfer,adjustment',
            'points_amount' => 'required|integer|not_in:0',
            'description' => 'required|string|max:255',
            'reference_id' => 'nullable|string|max:100',
            'purchase_amount' => 'nullable|numeric|min:0',
            'purchase_currency' => 'nullable|string|max:3',
            'points_multiplier' => 'nullable|numeric|min:0',
            'expires_at' => 'nullable|date|after:now',
            'metadata' => 'nullable|array',
            'webhook_data' => 'nullable|array',
            'process_immediately' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'member_id.required' => 'Member ID is required',
            'member_id.exists' => 'Member does not exist',
            'member_passkit_id.required' => 'Member Wallet ID is required',
            'member_passkit_id.exists' => 'Member Wallet ID does not exist',
            'account_id.required' => 'Account ID is required',
            'account_id.exists' => 'Account does not exist',
            'transaction_type.required' => 'Transaction type is required',
            'transaction_type.in' => 'Transaction type must be one of: earn, burn, expire, transfer, adjustment',
            'points_amount.required' => 'Points amount is required',
            'points_amount.not_in' => 'Points amount cannot be zero',
            'description.required' => 'Transaction description is required',
            'description.max' => 'Description cannot exceed 255 characters',
            'reference_id.max' => 'Reference ID cannot exceed 100 characters',
            'purchase_amount.min' => 'Purchase amount cannot be negative',
            'purchase_currency.max' => 'Currency code must be 3 characters or less',
            'points_multiplier.min' => 'Points multiplier cannot be negative',
            'expires_at.after' => 'Expiry date must be in the future',
        ];
    }

    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);
        
        // Set defaults
        $validated['status'] = 'pending';
        $validated['process_immediately'] = $validated['process_immediately'] ?? true;
        
        // Calculate points balance if member exists
        if (isset($validated['member_id'])) {
            $member = \ShakewellAgency\PassKitLaravel\Models\PassKitMember::find($validated['member_id']);
            if ($member) {
                $validated['points_balance_before'] = $member->points_balance;
                $validated['points_balance_after'] = $member->points_balance + $validated['points_amount'];
            }
        }
        
        // Set processed_at if processing immediately
        if ($validated['process_immediately']) {
            $validated['processed_at'] = now();
            $validated['status'] = 'completed';
        }
        
        // Clean up arrays
        if (isset($validated['metadata'])) {
            $validated['metadata'] = array_filter($validated['metadata'], function($value) {
                return $value !== null && $value !== '';
            });
        }
        
        if (isset($validated['webhook_data'])) {
            $validated['webhook_data'] = array_filter($validated['webhook_data'], function($value) {
                return $value !== null && $value !== '';
            });
        }
        
        return $validated;
    }
}