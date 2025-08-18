<?php

namespace ShakewellAgency\PassKitLaravel\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    public function rules(): array
    {
        $memberId = $this->route('member')->id ?? null;
        
        return [
            'external_id' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('passkit_members', 'external_id')->ignore($memberId)
            ],
            'tier_id' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255',
            'first_name' => 'sometimes|string|max:100',
            'last_name' => 'sometimes|string|max:100',
            'phone' => 'nullable|string|max:20',
            'date_of_birth' => 'nullable|date|before:today',
            'gender' => 'nullable|in:male,female,other,prefer_not_to_say',
            'address_line1' => 'nullable|string|max:255',
            'address_line2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:3',
            'status' => 'sometimes|in:active,inactive,suspended',
            'preferences' => 'nullable|array',
            'preferences.language' => 'nullable|string|max:5',
            'preferences.currency' => 'nullable|string|max:3',
            'preferences.notifications' => 'nullable|boolean',
            'preferences.marketing' => 'nullable|boolean',
            'custom_fields' => 'nullable|array',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
        ];
    }

    public function messages(): array
    {
        return [
            'external_id.unique' => 'A member with this external ID already exists',
            'email.email' => 'Please provide a valid email address',
            'date_of_birth.before' => 'Date of birth must be before today',
            'gender.in' => 'Gender must be one of: male, female, other, prefer_not_to_say',
            'country.max' => 'Country code must be 3 characters or less',
            'status.in' => 'Status must be one of: active, inactive, suspended',
            'preferences.language.max' => 'Language code must be 5 characters or less',
            'preferences.currency.max' => 'Currency code must be 3 characters or less',
            'tags.*.max' => 'Each tag must be 50 characters or less',
        ];
    }

    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);
        
        // Clean up arrays
        if (isset($validated['preferences'])) {
            $validated['preferences'] = array_filter($validated['preferences'], function($value) {
                return $value !== null && $value !== '';
            });
        }
        
        if (isset($validated['custom_fields'])) {
            $validated['custom_fields'] = array_filter($validated['custom_fields'], function($value) {
                return $value !== null && $value !== '';
            });
        }
        
        if (isset($validated['tags'])) {
            $validated['tags'] = array_filter($validated['tags'], function($value) {
                return $value !== null && $value !== '';
            });
        }
        
        return $validated;
    }
}