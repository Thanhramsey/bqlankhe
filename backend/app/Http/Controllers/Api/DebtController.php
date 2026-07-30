<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CollectionRoute;
use App\Models\Household;
use App\Models\User;
use App\Models\AuditLog;
use App\Services\ExcelExportService;
use App\Services\ServicePricingService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DebtController extends Controller
{
    public function __construct(private readonly ServicePricingService $pricing) {}

    public function export(Request $request, ExcelExportService $excel): BinaryFileResponse
    {
        $data = $this->index($request)->getData(true)['data'];
        $rows = collect($data['items'])->map(fn ($item, $index) => [
            $index + 1, $item['sequence_number'], $item['code'], $item['owner_name'], $item['phone'], $item['address'],
            $item['route']['name'] ?? '', collect($item['collectors'])->pluck('name')->join(', '),
            $item['oldest_debt_month'], $item['latest_debt_month'], $item['debt_months'], $item['overdue_months'], (float) $item['total_debt'],
            $item['over_six_months'] ? 'Nợ trên 6 tháng' : 'Đang nợ',
        ])->all();
        $path = $excel->create('Danh sách hộ chưa thu', ['STT', 'Số thứ tự', 'Mã hộ', 'Hộ dân', 'Số điện thoại', 'Địa chỉ', 'Tuyến thu', 'Nhân viên', 'Nợ từ tháng', 'Đến tháng', 'Số tháng nợ', 'Quá hạn', 'Tổng tiền nợ', 'Cảnh báo'], $rows, [8, 12, 16, 25, 17, 36, 24, 24, 15, 15, 14, 14, 20, 20], ['M']);
        AuditLog::create(['user_id' => $request->user()->id, 'action' => 'EXPORT_DEBTS', 'entity_type' => Household::class, 'new_values' => ['total' => count($rows), 'filters' => $request->only(['collection_route_id', 'collector_id', 'from_month', 'to_month', 'over_six_months'])], 'ip_address' => $request->ip()]);
        return response()->download($path, 'danh-sach-chua-thu-'.now()->format('Ymd-His').'.xlsx')->deleteFileAfterSend();
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'collection_route_id' => ['nullable', 'integer', 'exists:collection_routes,id'],
            'collector_id' => ['nullable', 'integer', 'exists:users,id'],
            'from_month' => ['nullable', 'date_format:Y-m'],
            'to_month' => ['nullable', 'date_format:Y-m'],
            'over_six_months' => ['nullable', 'boolean'],
        ]);

        $to = isset($validated['to_month'])
            ? CarbonImmutable::createFromFormat('Y-m', $validated['to_month'])->startOfMonth()
            : CarbonImmutable::now()->startOfMonth();
        $from = isset($validated['from_month'])
            ? CarbonImmutable::createFromFormat('Y-m', $validated['from_month'])->startOfMonth()
            : null;
        if ($from?->greaterThan($to)) {
            return response()->json(['success' => false, 'message' => 'Tháng bắt đầu không được lớn hơn tháng kết thúc.', 'data' => null], 422);
        }

        $query = Household::query()->where('is_active', true)
            ->with([
                'route:id,code,name',
                'route.users:id,name',
                'services' => fn ($query) => $query->with('service:id,name,monthly_price,tax_fee'),
                'services.paymentMonths' => fn ($query) => $query->whereDate('month', '<=', $to),
            ])
            ->whereHas('services', fn ($query) => $query->whereDate('started_at', '<=', $to));

        if (! empty($validated['collection_route_id'])) {
            $query->where('collection_route_id', $validated['collection_route_id']);
        }
        if (! empty($validated['collector_id'])) {
            $query->whereHas('route.users', fn ($query) => $query->where('users.id', $validated['collector_id']));
        }

        $todayMonth = CarbonImmutable::now()->startOfMonth();
        $items = $query->orderBy('sequence_number')->orderBy('owner_name')->get()->map(function (Household $household) use ($from, $to, $todayMonth) {
            $unpaid = collect();
            foreach ($household->services as $subscription) {
                $start = CarbonImmutable::parse($subscription->started_at)->startOfMonth();
                if ($from && $start->lessThan($from)) $start = $from;
                $end = $subscription->ended_at ? CarbonImmutable::parse($subscription->ended_at)->startOfMonth() : $to;
                if ($end->greaterThan($to)) $end = $to;
                if ($start->greaterThan($end)) continue;

                $paidMonths = $subscription->paymentMonths->pluck('month')->map(fn ($month) => CarbonImmutable::parse($month)->format('Y-m'))->flip();
                for ($month = $start; $month->lessThanOrEqualTo($end); $month = $month->addMonth()) {
                    if (! $paidMonths->has($month->format('Y-m'))) {
                        $unpaid->push(['month' => $month, 'amount' => $this->pricing->values($subscription->service, $month)['total']]);
                    }
                }
            }

            if ($unpaid->isEmpty()) return null;
            $oldest = $unpaid->sortBy(fn ($item) => $item['month']->timestamp)->first()['month'];
            $overdueMonths = $oldest->lessThanOrEqualTo($todayMonth) ? $oldest->diffInMonths($todayMonth) + 1 : 0;

            return [
                'id' => $household->id,
                'code' => $household->code,
                'sequence_number' => $household->sequence_number,
                'owner_name' => $household->owner_name,
                'phone' => $household->phone,
                'address' => $household->address,
                'route' => $household->route,
                'collectors' => $household->route?->users?->map->only(['id', 'name'])->values() ?? [],
                'debt_months' => $unpaid->count(),
                'total_debt' => round($unpaid->sum('amount'), 2),
                'oldest_debt_month' => $oldest->format('Y-m'),
                'latest_debt_month' => $unpaid->sortByDesc(fn ($item) => $item['month']->timestamp)->first()['month']->format('Y-m'),
                'overdue_months' => $overdueMonths,
                'over_six_months' => $unpaid->count() > 6,
            ];
        })->filter()->values();

        if ($request->boolean('over_six_months')) $items = $items->where('over_six_months', true)->values();

        return response()->json(['success' => true, 'message' => 'Thành công', 'data' => [
            'summary' => [
                'households_in_debt' => $items->count(),
                'total_debt' => round($items->sum('total_debt'), 2),
                'over_six_months' => $items->where('over_six_months', true)->count(),
                'total_debt_months' => $items->sum('debt_months'),
            ],
            'items' => $items,
            'options' => [
                'routes' => CollectionRoute::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
                'collectors' => User::query()->where('is_active', true)->whereHas('collectionRoutes')->orderBy('name')->get(['id', 'name']),
            ],
        ]]);
    }
}
