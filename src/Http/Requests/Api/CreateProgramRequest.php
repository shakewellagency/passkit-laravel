<?php

namespace ShakewellAgency\PassKitLaravel\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class CreateProgramRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'program_type' => 'required|in:membership,loyalty,points,cashback',
            'account_id' => 'required|integer|exists:accounts,id',
            'status' => 'nullable|in:active,inactive,draft',
            'settings' => 'nullable|array',
            'settings.points_per_dollar' => 'nullable|numeric|min:0',
            'settings.welcome_points' => 'nullable|integer|min:0',
            'settings.minimum_redemption' => 'nullable|integer|min:1',
            'settings.points_expiry_days' => 'nullable|integer|min:0',
            'settings.auto_enroll' => 'nullable|boolean',
            'settings.max_balance' => 'nullable|integer|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Program name is required',
            'name.max' => 'Program name cannot exceed 255 characters',
            'description.max' => 'Program description cannot exceed 1000 characters',
            'program_type.required' => 'Program type is required',
            'program_type.in' => 'Program type must be one of: membership, loyalty, points, cashback',
            'account_id.required' => 'Account ID is required',
            'account_id.exists' => 'Account does not exist',
            'status.in' => 'Status must be one of: active, inactive, draft',
            'settings.points_per_dollar.numeric' => 'Points per dollar must be a number',
            'settings.welcome_points.integer' => 'Welcome points must be an integer',
            'settings.minimum_redemption.min' => 'Minimum redemption must be at least 1',
            'settings.points_expiry_days.min' => 'Points expiry days cannot be negative',
        ];
    }

    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);
        
        // Ensure settings is properly formatted
        if (isset($validated['settings'])) {
            $validated['settings'] = array_filter($validated['settings'], function($value) {
                return $value !== null && $value !== '';
            });
        }
        
        return $validated;
    }
}