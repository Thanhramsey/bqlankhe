<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DashboardFilterRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->hasPermission('dashboard.view') ?? false; }

    public function rules(): array
    {
        return [
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'route_id' => ['nullable', 'integer', 'exists:collection_routes,id'],
            'collector_id' => ['nullable', 'integer', 'exists:users,id'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'payment_status' => ['nullable', 'string', 'max:50'],
            'invoice_status' => ['nullable', 'string', 'max:50'],
            'chart_range' => ['nullable', 'in:6,12,year'],
            'collector_period' => ['nullable', 'in:today,month,quarter'],
            'collector_rank_by' => ['nullable', 'in:revenue,completion'],
        ];
    }
}
