<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveHouseholdRequest;
use App\Http\Resources\HouseholdResource;
use App\Models\AuditLog;
use App\Models\CollectionRoute;
use App\Models\Household;
use App\Models\Service;
use App\Services\HouseholdService;
use App\Services\ExcelExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class HouseholdController extends Controller
{
    public function __construct(private readonly HouseholdService $service) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('households.manage'), 403);
        $query = Household::query()->with(['route:id,code,name', 'services' => fn ($query) => $query->where('is_active', true)->with('service:id,code,name,monthly_price,tax_fee')]);
        if ($request->boolean('with_deleted')) {
            $query->withTrashed();
        }
        if ($request->filled('search')) {
            $term = '%'.trim($request->string('search')).'%';
            $query->where(function ($query) use ($term) {
                $query->whereAny(['code', 'owner_name', 'phone', 'identity_number', 'email', 'tax_code', 'representative', 'address', 'invoice_address'], 'like', $term)
                    ->orWhereHas('route', fn ($route) => $route->where('name', 'like', $term)->orWhere('code', 'like', $term))
                    ->orWhereHas('services.service', fn ($service) => $service->where('name', 'like', $term)->orWhere('code', 'like', $term));
            });
        }
        if ($request->filled('collection_route_id')) {
            $query->where('collection_route_id', $request->integer('collection_route_id'));
        }
        if ($request->filled('service_id')) {
            $query->whereHas('services', fn ($service) => $service
                ->where('service_id', $request->integer('service_id'))
                ->where('is_active', true));
        }
        $households = $query->orderByRaw('sequence_number IS NULL')->orderBy('sequence_number')->latest('id')->paginate(min($request->integer('per_page', 30), 100));

        return response()->json(['success' => true, 'message' => 'Thành công', 'data' => [
            ...$households->toArray(),
            'data' => HouseholdResource::collection($households->getCollection())->resolve(),
        ]]);
    }

    public function options(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('households.manage'), 403);

        return response()->json(['success' => true, 'message' => 'Thành công', 'data' => [
            'routes' => CollectionRoute::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'services' => Service::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name', 'monthly_price', 'tax_fee']),
        ]]);
    }

    public function export(Request $request, ExcelExportService $excel): BinaryFileResponse
    {
        abort_unless($request->user()->hasPermission('households.manage'), 403);
        $query = Household::query()->with(['route:id,code,name', 'services.service:id,code,name']);
        if ($request->filled('search')) {
            $term = '%'.trim((string) $request->input('search')).'%';
            $query->where(fn ($query) => $query->whereAny(['code', 'owner_name', 'phone', 'identity_number', 'email', 'tax_code', 'representative', 'address', 'invoice_address'], 'like', $term));
        }
        if ($request->filled('collection_route_id')) $query->where('collection_route_id', $request->integer('collection_route_id'));
        if ($request->filled('service_id')) $query->whereHas('services', fn ($service) => $service->where('service_id', $request->integer('service_id'))->where('is_active', true));
        $households = $query->orderByRaw('sequence_number IS NULL')->orderBy('sequence_number')->orderBy('owner_name')->get();
        $rows = $households->map(fn ($item, $index) => [
            $index + 1, $item->sequence_number, $item->code, $item->owner_name, $item->phone, $item->address, $item->invoice_address,
            $item->identity_number, $item->route?->name, $item->services->first()?->service?->name,
            $item->email, $item->tax_code, $item->representative, $item->note, $item->is_active ? 'Hoạt động' : 'Ngừng hoạt động',
        ])->all();
        $path = $excel->create('Danh sách hộ dân', ['STT', 'Số thứ tự', 'Mã hộ', 'Họ tên', 'Số điện thoại', 'Địa chỉ', 'Địa chỉ HĐ', 'CCCD', 'Tuyến thu', 'Loại dịch vụ', 'Email', 'Mã số thuế', 'Người đại diện', 'Ghi chú', 'Trạng thái'], $rows, [8, 12, 16, 25, 17, 38, 38, 20, 24, 28, 28, 20, 24, 30, 18]);
        AuditLog::create(['user_id' => $request->user()->id, 'action' => 'EXPORT_HOUSEHOLDS', 'entity_type' => Household::class, 'new_values' => ['total' => count($rows)], 'ip_address' => $request->ip()]);
        return response()->download($path, 'danh-sach-ho-dan-'.now()->format('Ymd-His').'.xlsx')->deleteFileAfterSend();
    }

    public function store(SaveHouseholdRequest $request): JsonResponse
    {
        $household = $this->service->create($request->validated());
        $this->audit($request, 'CREATE', $household);

        return response()->json(['success' => true, 'message' => 'Đã thêm hộ dân.', 'data' => new HouseholdResource($household)], 201);
    }

    public function update(SaveHouseholdRequest $request, Household $household): JsonResponse
    {
        $old = $household->load('services')->toArray();
        $household = $this->service->update($household, $request->validated());
        $this->audit($request, 'UPDATE', $household, $old);

        return response()->json(['success' => true, 'message' => 'Đã cập nhật hộ dân.', 'data' => new HouseholdResource($household)]);
    }

    public function destroy(Request $request, Household $household): JsonResponse
    {
        abort_unless($request->user()->hasPermission('households.manage'), 403);
        $this->audit($request, 'DELETE', $household, $household->toArray());
        $household->delete();

        return response()->json(['success' => true, 'message' => 'Đã xóa hộ dân.', 'data' => null]);
    }

    public function restore(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()->hasPermission('households.manage'), 403);
        $household = Household::withTrashed()->findOrFail($id);
        $household->restore();
        $this->audit($request, 'RESTORE', $household);

        return response()->json(['success' => true, 'message' => 'Đã khôi phục hộ dân.', 'data' => new HouseholdResource($household->load(['route', 'services.service']))]);
    }

    public function payments(Request $request, Household $household): JsonResponse
    {
        abort_unless($request->user()->hasPermission('households.manage'), 403);
        $payments = $household->payments()->with(['collector:id,name', 'invoice:id,payment_id,invoice_no,status', 'months.pricePeriod:id,document_number,document_name,effective_from,effective_to,monthly_price,tax_fee'])->latest('paid_at')->paginate(20);

        return response()->json(['success' => true, 'message' => 'Thành công', 'data' => $payments]);
    }

    private function audit(Request $request, string $action, Household $household, ?array $old = null): void
    {
        AuditLog::create(['user_id' => $request->user()->id, 'action' => $action, 'entity_type' => Household::class, 'entity_id' => $household->id, 'old_values' => $old, 'new_values' => $action === 'DELETE' ? null : $household->toArray(), 'ip_address' => $request->ip()]);
    }
}
