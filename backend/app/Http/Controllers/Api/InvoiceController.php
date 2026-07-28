<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\InvoiceSettingService;
use App\Services\VnptInvoiceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InvoiceController extends Controller
{
    public function publish(Request $request, VnptInvoiceService $service): JsonResponse
    {
        $data = $request->validate(['payment_ids' => ['required', 'array', 'min:1'], 'payment_ids.*' => ['integer', 'distinct', 'exists:payments,id']]);
        $results = collect($data['payment_ids'])->map(function ($id) use ($service) {
            try { return ['success' => true, ...$service->publish(Payment::findOrFail($id))]; }
            catch (\Throwable $exception) { return ['success' => false, 'payment_id' => $id, 'message' => $exception->getMessage()]; }
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
