<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Services\ExcelExportService;
use App\Services\RevenueReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    public function __construct(private readonly RevenueReportService $reports) {}

    public function revenue(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'message' => 'Thành công', 'data' => $this->reports->generate($request)]);
    }

    public function excel(Request $request, ExcelExportService $excel): BinaryFileResponse
    {
        $report = $this->reports->generate($request);
        if ($report['filters']['report_type'] === 'detail') {
            $headers = ['STT', 'Ngày ghi nhận', 'Mã phiếu', 'Số hóa đơn', 'Mã hộ', 'Hộ dân', 'Tuyến thu', 'Nhân viên thu', 'Từ tháng', 'Đến tháng', 'Số tiền'];
            $rows = collect($report['details'])->map(fn ($item, $index) => [$index + 1, $item['date'], $item['code'], $item['invoice_no'], $item['household_code'], $item['household_name'], $item['route_name'], $item['collector_name'], $item['from_month'], $item['to_month'], $item['amount']])->all();
            $path = $excel->create('Báo cáo doanh thu chi tiết', $headers, $rows, [8, 22, 22, 18, 16, 26, 24, 22, 14, 14, 20], ['K']);
        } else {
            $headers = ['STT', 'Nhóm báo cáo', 'Số giao dịch', 'Số hộ dân', 'Doanh thu'];
            $rows = collect($report['groups'])->map(fn ($item, $index) => [$index + 1, $item['label'], $item['transactions'], $item['households'], $item['amount']])->all();
            $path = $excel->create('Báo cáo doanh thu tổng hợp', $headers, $rows, [8, 32, 18, 18, 22], ['E']);
        }
        $this->audit($request, 'EXPORT_REPORT_EXCEL', $report);
        return response()->download($path, 'bao-cao-doanh-thu-'.now()->format('Ymd-His').'.xlsx')->deleteFileAfterSend();
    }

    public function pdf(Request $request): Response
    {
        $report = $this->reports->generate($request);
        $this->audit($request, 'EXPORT_REPORT_PDF', $report);
        return Pdf::loadView('pdf.revenue-report', ['report' => $report])->setPaper('a4', 'landscape')->download('bao-cao-doanh-thu-'.now()->format('Ymd-His').'.pdf');
    }

    private function audit(Request $request, string $action, array $report): void
    {
        AuditLog::create(['user_id' => $request->user()->id, 'action' => $action, 'entity_type' => Payment::class, 'new_values' => ['filters' => $report['filters'], 'transactions' => $report['summary']['transactions'], 'total_revenue' => $report['summary']['total_revenue']], 'ip_address' => $request->ip()]);
    }
}
