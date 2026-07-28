<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CollectPaymentRequest;
use App\Models\Household;
use App\Models\CollectionRoute;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(private PaymentService $service) {}

    public function index(Request $r): JsonResponse
    {
        $query = Payment::with(['household.route', 'invoice']);
        if ($r->filled('collection_route_id')) {
            $query->whereHas('household', fn ($household) => $household->where('collection_route_id', $r->integer('collection_route_id')));
        }
        $data = $query->latest('paid_at')->paginate(min((int) $r->input('per_page', 15), 100));

        return response()->json(['success' => true, 'message' => 'Thành công', 'data' => $data]);
    }

    public function store(CollectPaymentRequest $r): JsonResponse
    {
        $data = $this->service->collectMany($r->validated(), $r->user()->id, $r->ip());

        return response()->json(['success' => true, 'message' => 'Đã thu phí cho '.$data->count().' hộ dân.', 'data' => $data], 201);
    }

    public function options(Request $request): JsonResponse
    {
        $households = Household::query()->where('is_active', true)
            ->with('route:id,code,name')
            ->whereHas('services', fn ($service) => $service->where('is_active', true));
        if ($request->filled('collection_route_id')) {
            $households->where('collection_route_id', $request->integer('collection_route_id'));
        }

        return response()->json(['success' => true, 'message' => 'Thành công', 'data' => [
            'routes' => CollectionRoute::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'households' => $households->orderByRaw('sequence_number IS NULL')->orderBy('sequence_number')->get(['id', 'collection_route_id', 'code', 'owner_name', 'address']),
        ]]);
    }

    public function suggestion(Household $household): JsonResponse
    {
        return response()->json(['success' => true, 'message' => 'Thành công', 'data' => ['next_month' => $this->service->nextSuggestedMonth($household)]]);
    }
}
