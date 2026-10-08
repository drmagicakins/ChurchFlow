<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Member::class);
    }

    public function rules(): array
    {
        $churchId = $this->user()->church_id;

        return [
            'full_name' => ['required', 'string', 'max:255'],
            'email' => [
                'nullable', 'email', 'max:255',
                Rule::unique('members', 'email')->where('church_id', $churchId),
            ],
            'organizational_unit_id' => [
                'nullable',
                Rule::exists('organizational_units', 'id')->where('church_id', $churchId),
            ],
            'gender' => ['nullable', 'in:male,female,other'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:2000'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:30'],
            'membership_status' => ['nullable', 'in:active,inactive,visitor,transferred,deceased,draft'],
            'date_joined' => ['nullable', 'date'],
            'baptism_date' => ['nullable', 'date'],
            'is_worker' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
