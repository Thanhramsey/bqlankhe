<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DashboardFilterRequest;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __invoke(DashboardFilterRequest $request, DashboardService $service): JsonResponse
    {
        return response()->json(['success' => true, 'message' => 'Thành công', 'data' => $service->get($request->validated())]);
    }
}
