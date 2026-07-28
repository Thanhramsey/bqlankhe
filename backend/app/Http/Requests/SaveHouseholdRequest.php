<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveHouseholdRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('households.manage') ?? false;
    }

    public function rules(): array
    {
        $id = $this->route('household')?->id;

        return [
            'code' => ['required', 'string', 'max:50', Rule::unique('households')->ignore($id)],
            'sequence_number' => ['nullable', 'integer', 'min:1'],
            'owner_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'identity_number' => ['nullable', 'string', 'max:20', Rule::unique('households')->ignore($id)],
            'email' => ['nullable', 'email', 'max:255'],
            'tax_code' => ['nullable', 'string', 'max:30', Rule::unique('households')->ignore($id)],
            'representative' => ['nullable', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'ward' => ['nullable', 'string', 'max:100'],
            'collection_route_id' => ['nullable', 'exists:collection_routes,id'],
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'note' => ['nullable', 'string'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
