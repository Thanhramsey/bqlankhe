<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Invoice;
use App\Models\AuditLog;
use App\Services\InvoiceSettingService;
use App\Services\VnptInvoiceService;
use App\Services\ExcelExportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class InvoiceController extends Controller
{
    public function export(Request $request, ExcelExportService $excel): BinaryFileResponse
    {
        $data = [];
        $page = 1;
        do {
            $request->merge(['per_page' => 100, 'page' => $page]);
            $pagination = $this->index($request)->getData(true)['data']['items'];
            $data = [...$data, ...$pagination['data']];
            $page++;
        } while ($pagination['current_page'] < $pagination['last_page']);
        $statusLabels = ['CHO_PHAT_HANH' => 'Chờ phát hành', 'DANG_PHAT_HANH' => 'Đang phát hành', 'DA_PHAT_HANH' => 'Đã phát hành', 'PHAT_HANH_LOI' => 'Lỗi phát hành'];
        $rows = collect($data)->map(fn ($item, $index) => [
            $index + 1, $item['payment']['code'] ?? '', $item['invoice_no'] ?? '',
            $item['payment']['household']['code'] ?? '', $item['payment']['household']['owner_name'] ?? '',
            $item['payment']['household']['phone'] ?? '', $item['payment']['household']['address'] ?? '',
            $item['payment']['household']['route']['name'] ?? '', $item['payment']['collector']['name'] ?? '', $item['issuer']['name'] ?? '',
            isset($item['payment']['from_month']) ? substr($item['payment']['from_month'], 0, 7) : '',
            isset($item['payment']['to_month']) ? substr($item['payment']['to_month'], 0, 7) : '',
            (float) ($item['payment']['amount'] ?? 0), $statusLabels[$item['status']] ?? $item['status'],
            $item['issued_at'] ?? '',
        ])->all();
        $path = $excel->create('Danh sách hóa đơn điện tử', ['STT', 'Mã phiếu', 'Số hóa đơn', 'Mã hộ', 'Hộ dân', 'Số điện thoại', 'Địa chỉ', 'Tuyến thu', 'Người thu', 'Người phát hành', 'Từ tháng', 'Đến tháng', 'Số tiền', 'Trạng thái', 'Ngày phát hành'], $rows, [8, 22, 18, 16, 25, 17, 36, 24, 22, 22, 14, 14, 20, 20, 22], ['L']);
        AuditLog::create(['user_id' => $request->user()->id, 'action' => 'EXPORT_INVOICES', 'entity_type' => Invoice::class, 'new_values' => ['total' => count($rows), 'filters' => $request->only(['search', 'status', 'collection_route_id'])], 'ip_address' => $request->ip()]);
        return response()->download($path, 'danh-sach-hoa-don-dien-tu-'.now()->format('Ymd-His').'.xlsx')->deleteFileAfterSend();
    }

    public function index(Request $request): JsonResponse
    {
        $query = Invoice::query()->with([
            'issuer:id,name',
            'payment:id,code,household_id,collector_id,from_month,to_month,amount,paid_at',
            'payment.household:id,collection_route_id,code,owner_name,phone,address',
            'payment.household.route:id,code,name',
            'payment.collector:id,name',
        ]);

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($query) use ($search) {
                $query->where('invoice_no', 'like', "%{$search}%")
                    ->orWhereHas('payment', fn ($payment) => $payment->where('code', 'like', "%{$search}%")
                        ->orWhereHas('household', fn ($household) => $household
                            ->where('code', 'like', "%{$search}%")
                            ->orWhere('owner_name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%")));
            });
        }
        if ($request->filled('status')) $query->where('status', $request->input('status'));
        if ($request->filled('collection_route_id')) {
            $query->whereHas('payment.household', fn ($household) => $household->where('collection_route_id', $request->integer('collection_route_id')));
        }

        $summary = Invoice::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $items = $query->latest('id')->paginate(min($request->integer('per_page', 50), 100));

        return response()->json(['success' => true, 'message' => 'Thành công', 'data' => [
            'items' => $items,
            'summary' => [
                'total' => Invoice::count(),
                'pending' => (int) ($summary['CHO_PHAT_HANH'] ?? 0),
                'published' => (int) ($summary['DA_PHAT_HANH'] ?? 0),
                'failed' => (int) ($summary['PHAT_HANH_LOI'] ?? 0),
            ],
            'routes' => \App\Models\CollectionRoute::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
        ]]);
    }

    public function publish(Request $request, VnptInvoiceService $service): JsonResponse
    {
        $data = $request->validate(['payment_ids' => ['required', 'array', 'min:1'], 'payment_ids.*' => ['integer', 'distinct', 'exists:payments,id']]);
        $results = collect($data['payment_ids'])->map(function ($id) use ($service, $request) {
            $payment = Payment::findOrFail($id);
            try {
                $result = ['success' => true, ...$service->publish($payment, $request->user()->id)];
                AuditLog::create(['user_id' => $request->user()->id, 'action' => 'PUBLISH_INVOICE', 'entity_type' => Invoice::class, 'entity_id' => $result['invoice_id'], 'new_values' => ['payment_code' => $payment->code, 'invoice_no' => $result['invoice_no']], 'ip_address' => $request->ip()]);
                return $result;
            } catch (\Throwable $exception) {
                AuditLog::create(['user_id' => $request->user()->id, 'action' => 'PUBLISH_INVOICE_FAILED', 'entity_type' => Invoice::class, 'entity_id' => $payment->invoice?->id, 'new_values' => ['payment_code' => $payment->code, 'error' => $exception->getMessage()], 'ip_address' => $request->ip()]);
                return ['success' => false, 'payment_id' => $id, 'message' => $exception->getMessage()];
            }
        });
        $success = $results->where('success', true)->count();
        return response()->json(['success' => true, 'message' => "Đã phát hành {$success}/{$results->count()} hóa đơn.", 'data' => $results]);
    }

    public function receipt(Request $request, Payment $payment, InvoiceSettingService $settings): Response
    {
        $payment->load(['household.route', 'months', 'collector']);
        return Pdf::loadView('pdf.receipt', ['payment' => $payment, 'settings' => collect($settings->forUi())->pluck('value', 'key')])->setPaper('a5')->stream('phieu-thu-'.$payment->code.'.pdf');
    }

    public function invoice(Request $request, Payment $payment, VnptInvoiceService $service): Response
    {
        $download = $service->downloadOfficialInvoice($payment);
        return response($download['content'], 200, ['Content-Type' => $download['mime'], 'Content-Disposition' => 'inline; filename="'.$download['filename'].'"']);
    }
}
