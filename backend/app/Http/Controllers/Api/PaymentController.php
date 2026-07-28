<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CollectPaymentRequest;
use App\Models\Household;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(private PaymentService $service) {}

    public function index(Request $r): JsonResponse
    {
        $data = Payment::with(['household', 'invoice'])->latest('paid_at')->paginate(min((int) $r->input('per_page', 15), 100));

        return response()->json(['success' => true, 'message' => 'Thành công', 'data' => $data]);
    }

    public function store(CollectPaymentRequest $r): JsonResponse
    {
        $data = $this->service->collect($r->validated(), $r->user()->id, $r->ip());

        return response()->json(['success' => true, 'message' => 'Thu phí thành công.', 'data' => $data], 201);
    }

    public function suggestion(Household $household): JsonResponse
    {
        return response()->json(['success' => true, 'message' => 'Thành công', 'data' => ['next_month' => $this->service->nextSuggestedMonth($household)]]);
    }
}
