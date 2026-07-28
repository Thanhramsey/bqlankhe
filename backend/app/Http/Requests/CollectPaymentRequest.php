<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CollectPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['household_id' => ['required', 'exists:households,id'], 'from_month' => ['required', 'date_format:Y-m'], 'to_month' => ['required', 'date_format:Y-m', 'after_or_equal:from_month'], 'payment_method' => ['nullable', 'in:TIEN_MAT,CHUYEN_KHOAN'], 'note' => ['nullable', 'string', 'max:1000']];
    }
}
