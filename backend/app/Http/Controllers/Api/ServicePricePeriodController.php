<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\Service;
use App\Models\ServicePricePeriod;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServicePricePeriodController extends Controller
{
    public function index(Service $service): JsonResponse
    {
        return $this->ok([
            'service' => $service->only(['id', 'code', 'name', 'monthly_price', 'tax_fee']),
            'periods' => $service->pricePeriods()->with(['document:id,code,name,document_date', 'creator:id,name'])->get(),
            'documents' => Document::query()->where('is_active', true)->orderByDesc('document_date')->get(['id', 'code', 'name', 'document_date'])
                ->map(fn (Document $document) => [...$document->toArray(), 'display_name' => $document->code.' — '.$document->name]),
        ]);
    }

    public function store(Request $request, Service $service): JsonResponse
    {
        $data = $this->validated($request);
        $period = DB::transaction(function () use ($request, $service, $data) {
            $this->ensureNoOverlap($service, $data);
            $period = $service->pricePeriods()->create([...$data, 'created_by' => $request->user()->id]);
            $this->syncCurrentPrice($service);
            $this->audit($request, 'CREATE_SERVICE_PRICE', $period);
            return $period;
        });
        return response()->json(['success' => true, 'message' => 'Đã thêm giai đoạn áp giá.', 'data' => $period->load(['document:id,code,name', 'creator:id,name'])], 201);
    }

    public function update(Request $request, Service $service, ServicePricePeriod $period): JsonResponse
    {
        abort_unless($period->service_id === $service->id, 404);
        $data = $this->validated($request);
        DB::transaction(function () use ($request, $service, $period, $data) {
            $old = $period->toArray();
            $this->ensureNoOverlap($service, $data, $period->id);
            $period->update($data);
            $this->syncCurrentPrice($service);
            $this->audit($request, 'UPDATE_SERVICE_PRICE', $period, $old);
        });
        return $this->ok($period->fresh()->load(['document:id,code,name', 'creator:id,name']));
    }

    public function destroy(Request $request, Service $service, ServicePricePeriod $period): JsonResponse
    {
        abort_unless($period->service_id === $service->id, 404);
        if ($period->paymentMonths()->exists()) throw ValidationException::withMessages(['period' => 'Mức giá đã được dùng để thu tiền nên không thể xóa; hãy chuyển sang ngừng hoạt động.']);
        $old = $period->toArray();
        $period->delete();
        $this->syncCurrentPrice($service);
        $this->audit($request, 'DELETE_SERVICE_PRICE', $period, $old);
        return $this->ok(null);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'document_id' => 'nullable|integer|exists:documents,id', 'document_number' => 'required|string|max:100',
            'document_name' => 'nullable|string|max:255', 'document_date' => 'nullable|date',
            'effective_from' => 'required|date_format:Y-m-d', 'effective_to' => 'nullable|date_format:Y-m-d|after_or_equal:effective_from',
            'monthly_price' => 'required|numeric|min:0', 'tax_fee' => 'required|numeric|between:0,100',
            'note' => 'nullable|string|max:2000', 'is_active' => 'boolean',
        ]);
        $data['effective_from'] = CarbonImmutable::createFromFormat('Y-m-d', $data['effective_from'])->toDateString();
        $data['effective_to'] = empty($data['effective_to']) ? null : CarbonImmutable::createFromFormat('Y-m-d', $data['effective_to'])->toDateString();
        return $data;
    }

    private function ensureNoOverlap(Service $service, array $data, ?int $ignore = null): void
    {
        if (! ($data['is_active'] ?? true)) return;
        $end = $data['effective_to'] ?? '9999-12-31';
        $overlap = $service->pricePeriods()->where('is_active', true)->when($ignore, fn ($q) => $q->whereKeyNot($ignore))
            ->whereDate('effective_from', '<=', $end)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $data['effective_from']))->exists();
        if ($overlap) throw ValidationException::withMessages(['effective_from' => 'Khoảng áp dụng bị trùng với một giai đoạn giá đang hoạt động.']);
    }

    private function syncCurrentPrice(Service $service): void
    {
        $today = now()->startOfDay();
        $period = $service->pricePeriods()->where('is_active', true)->whereDate('effective_from', '<=', $today)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $today))->latest('effective_from')->first();
        if ($period) $service->update(['monthly_price' => $period->monthly_price, 'tax_fee' => $period->tax_fee]);
    }

    private function audit(Request $request, string $action, ServicePricePeriod $period, ?array $old = null): void
    {
        AuditLog::create(['user_id' => $request->user()->id, 'action' => $action, 'entity_type' => ServicePricePeriod::class, 'entity_id' => $period->id, 'old_values' => $old, 'new_values' => $action === 'DELETE_SERVICE_PRICE' ? null : $period->toArray(), 'ip_address' => $request->ip()]);
    }

    private function ok(mixed $data, string $message = 'Thành công'): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data]);
    }
}
