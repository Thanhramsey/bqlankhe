<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CollectionRoute;
use App\Models\Household;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\InvoiceSettingService;
use App\Services\PaymentService;
use App\Services\VnptInvoiceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MobileController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    public function routes(Request $request): JsonResponse
    {
        $ids = $this->routeIds($request);
        $query = CollectionRoute::where('is_active', true)->withCount(['households as household_count' => fn ($q) => $q->where('is_active', true)]);
        if ($ids->isNotEmpty()) {
            $query->whereIn('id', $ids);
        }

        return $this->ok($query->orderBy('name')->get(['id', 'code', 'name']));
    }

    public function households(Request $request, ?CollectionRoute $route = null): JsonResponse
    {
        $query = Household::where('is_active', true)->with([
            'route:id,code,name',
            'latestPayment' => fn ($q) => $q->select(['payments.id', 'payments.household_id', 'payments.from_month', 'payments.to_month', 'payments.paid_at']),
            'services' => fn ($q) => $q->where('is_active', true)->with('service:id,code,name,monthly_price,tax_fee'),
        ]);
        $this->scopeRoutes($query, $request);
        if ($route) {
            $ids = $this->routeIds($request);
            if ($ids->isNotEmpty()) {
                abort_unless($ids->contains($route->id), 403);
            } $query->where('collection_route_id', $route->id);
        } elseif ($request->filled('route_id')) {
            $query->where('collection_route_id', $request->integer('route_id'));
        }
        if ($request->filled('search')) {
            $term = '%'.trim($request->string('search')).'%';
            $query->where(fn ($q) => $q->whereAny(['code', 'owner_name', 'phone', 'address'], 'like', $term));
        }

        return $this->ok($query->orderByRaw('sequence_number IS NULL')->orderBy('sequence_number')->paginate(min($request->integer('per_page', 20), 50)));
    }

    public function household(Request $request, Household $household): JsonResponse
    {
        $this->authorizeHousehold($request, $household);

        return $this->ok($household->load(['route:id,code,name', 'services.service:id,code,name,monthly_price,tax_fee', 'payments' => fn ($q) => $q->with('invoice')->latest('paid_at')->limit(10)]));
    }

    public function history(Request $request, Household $household): JsonResponse
    {
        $this->authorizeHousehold($request, $household);

        return $this->ok($household->payments()->with(['invoice', 'months'])->latest('paid_at')->paginate(20));
    }

    public function suggestion(Request $request, Household $household): JsonResponse
    {
        $this->authorizeHousehold($request, $household);

        $latest = $household->payments()->latest('to_month')->first(['id', 'from_month', 'to_month', 'paid_at']);

        return $this->ok(['next_month' => $this->payments->nextSuggestedMonth($household), 'latest_payment' => $latest]);
    }

    public function preview(Request $request): JsonResponse
    {
        $data = $this->paymentData($request);
        $this->authorizeHousehold($request, Household::findOrFail($data['household_id']));

        return $this->ok($this->payments->preview($data));
    }

    public function collect(Request $request): JsonResponse
    {
        $data = $this->paymentData($request, true);
        $this->authorizeHousehold($request, Household::findOrFail($data['household_id']));
        $payment = $this->payments->collect($data, $request->user()->id, $request->ip())->load(['household.route', 'invoice', 'months']);

        return response()->json(['success' => true, 'message' => 'Thu tiền thành công.', 'data' => $payment], 201);
    }

    public function transactions(Request $request): JsonResponse
    {
        $query = Payment::with(['household.route', 'invoice'])->where('collector_id', $request->user()->id);
        if ($request->filled('search')) {
            $term = '%'.trim($request->string('search')).'%';
            $query->whereHas('household', fn ($q) => $q->whereAny(['code', 'owner_name', 'address'], 'like', $term));
        }

        return $this->ok($query->latest('paid_at')->paginate(min($request->integer('per_page', 20), 50)));
    }

    public function transaction(Request $request, Payment $payment): JsonResponse
    {
        $this->authorizePayment($request, $payment);

        return $this->ok($payment->load(['household.route', 'household.services.service', 'collector:id,name', 'months', 'invoice']));
    }

    public function issueInvoice(Request $request, Payment $payment, VnptInvoiceService $service): JsonResponse
    {
        $this->authorizePayment($request, $payment);
        try {
            $result = $service->publish($payment, $request->user()->id);
            AuditLog::create(['user_id' => $request->user()->id, 'action' => 'PUBLISH_INVOICE', 'entity_type' => Invoice::class, 'entity_id' => $result['invoice_id'], 'new_values' => ['payment_code' => $payment->code, 'invoice_no' => $result['invoice_no']], 'ip_address' => $request->ip()]);

            return $this->ok($payment->fresh()->load('invoice'));
        } catch (\Throwable $exception) {
            AuditLog::create(['user_id' => $request->user()->id, 'action' => 'PUBLISH_INVOICE_FAILED', 'entity_type' => Invoice::class, 'entity_id' => $payment->invoice?->id, 'new_values' => ['payment_code' => $payment->code, 'error' => $exception->getMessage()], 'ip_address' => $request->ip()]);
            throw $exception;
        }
    }

    public function receipt(Request $request, Payment $payment, InvoiceSettingService $settings): Response
    {
        $this->authorizePayment($request, $payment);
        $payment->load(['household.route', 'months', 'collector']);

        return Pdf::loadView('pdf.receipt', ['payment' => $payment, 'settings' => collect($settings->forUi())->pluck('value', 'key')])->setPaper('a5')->download('phieu-thu-'.$payment->code.'.pdf');
    }

    public function printData(Request $request, Payment $payment, InvoiceSettingService $settings): JsonResponse
    {
        $this->authorizePayment($request, $payment);
        $payment->load(['household.services.service', 'collector:id,name', 'invoice']);
        $values = collect($settings->forUi())->pluck('value', 'key');
        $household = $payment->household;
        $invoice = $payment->invoice;
        $fkey = $invoice?->provider_response['fkey'] ?? $payment->code;
        $lookup = trim((string) $values->get('Link tra cứu hóa đơn'));
        if ($lookup !== '' && $invoice?->status === 'DA_PHAT_HANH') {
            // The receipt displays the lookup portal and Fkey separately so
            // the printed URL stays short and readable on 58 mm paper.
            $lookup = str_replace(['?fkey={fkey}', '&fkey={fkey}', '{fkey}'], '', $lookup);
        } else {
            $lookup = null;
        }
        $bankCode = trim((string) $values->get('Mã Ngân Hàng'));
        $account = trim((string) $values->get('Số tài khoản ngân hàng'));
        $qrUrl = ($bankCode !== '' && $account !== '')
            ? 'https://img.vietqr.io/image/'.rawurlencode($bankCode).'-'.rawurlencode($account).'-qr_only.png?amount='.(int) $payment->amount.'&addInfo='.rawurlencode($payment->code).'&accountName='.rawurlencode((string) $values->get('Tên chủ tài khoản'))
            : null;

        return $this->ok([
            'organization' => ['name' => $values->get('Tên đơn vị'), 'address' => $values->get('Địa chỉ'), 'tax_code' => $values->get('Mã số thuế'), 'phone' => $values->get('Số điện thoại'), 'bank_account' => $account, 'bank_code' => $bankCode, 'account_name' => $values->get('Tên chủ tài khoản')],
            'receipt' => ['code' => $payment->code, 'paid_at' => $payment->paid_at, 'from_month' => $payment->from_month, 'to_month' => $payment->to_month, 'amount' => $payment->amount, 'collector_name' => $payment->collector?->name],
            'household' => ['code' => $household->code, 'name' => $household->owner_name, 'address' => $household->invoice_address ?: $household->address, 'service' => $household->services->first()?->service?->name],
            'payment_qr_url' => $qrUrl, 'invoice_lookup_url' => $lookup, 'invoice_fkey' => $invoice?->status === 'DA_PHAT_HANH' ? $fkey : null,
        ]);
    }

    public function invoice(Request $request, Payment $payment, VnptInvoiceService $service): Response
    {
        $this->authorizePayment($request, $payment);
        $download = $service->downloadOfficialInvoice($payment);

        return response($download['content'], 200, ['Content-Type' => $download['mime'], 'Content-Disposition' => 'attachment; filename="'.$download['filename'].'"']);
    }

    public function statistics(Request $request): JsonResponse
    {
        $from = $request->date('from_date')?->startOfDay() ?? now()->startOfDay();
        $to = $request->date('to_date')?->endOfDay() ?? now()->endOfDay();
        $query = Payment::where('collector_id', $request->user()->id)->whereBetween('paid_at', [$from, $to]);

        return $this->ok(['households' => (clone $query)->distinct()->count('household_id'), 'transactions' => (clone $query)->count(), 'total' => (float) (clone $query)->sum('amount'), 'cash' => (float) (clone $query)->where('payment_method', 'TIEN_MAT')->sum('amount'), 'bank_transfer' => (float) (clone $query)->where('payment_method', 'CHUYEN_KHOAN')->sum('amount')]);
    }

    private function paymentData(Request $request, bool $withPayment = false): array
    {
        return $request->validate(['household_id' => 'required|integer|exists:households,id', 'from_month' => 'required|date_format:Y-m', 'to_month' => 'required|date_format:Y-m|after_or_equal:from_month', 'payment_method' => [$withPayment ? 'required' : 'nullable', 'in:TIEN_MAT,CHUYEN_KHOAN,KHAC'], 'note' => 'nullable|string|max:1000']);
    }

    private function routeIds(Request $request)
    {
        $user = $request->user();

        return $user->collectionRoutes()->pluck('collection_routes.id')->when($user->collection_route_id, fn ($ids) => $ids->push($user->collection_route_id))->unique();
    }

    private function scopeRoutes(Builder $query, Request $request): void
    {
        $ids = $this->routeIds($request);
        if ($ids->isNotEmpty()) {
            $query->whereIn('collection_route_id', $ids);
        }
    }

    private function authorizeHousehold(Request $request, Household $household): void
    {
        $ids = $this->routeIds($request);
        if ($ids->isNotEmpty()) {
            abort_unless($ids->contains($household->collection_route_id), 403, 'Hộ dân không thuộc tuyến được phân công.');
        }
    }

    private function authorizePayment(Request $request, Payment $payment): void
    {
        $payment->loadMissing('household');
        if ($payment->collector_id === $request->user()->id) {
            return;
        }
        $this->authorizeHousehold($request, $payment->household);
    }

    private function ok(mixed $data): JsonResponse
    {
        return response()->json(['success' => true, 'message' => 'Thành công', 'data' => $data]);
    }
}
