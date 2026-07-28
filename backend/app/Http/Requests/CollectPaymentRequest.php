<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CollectPaymentRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->has('household_ids') && $this->filled('household_id')) {
            $this->merge(['household_ids' => [$this->input('household_id')]]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['household_ids' => ['required', 'array', 'min:1'], 'household_ids.*' => ['integer', 'distinct', 'exists:households,id'], 'from_month' => ['required', 'date_format:Y-m'], 'to_month' => ['required', 'date_format:Y-m', 'after_or_equal:from_month'], 'payment_method' => ['nullable', 'in:TIEN_MAT,CHUYEN_KHOAN'], 'note' => ['nullable', 'string', 'max:1000']];
    }
}
