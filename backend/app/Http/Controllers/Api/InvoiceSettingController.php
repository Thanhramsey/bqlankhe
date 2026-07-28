<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\InvoiceSettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceSettingController extends Controller
{
    public function __construct(private readonly InvoiceSettingService $service) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('settings.manage'), 403);
        return response()->json(['success' => true, 'message' => 'Thành công', 'data' => $this->service->forUi()]);
    }

    public function update(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('settings.manage'), 403);
        $data = $request->validate(['settings' => ['required', 'array'], 'settings.*' => ['nullable', 'string', 'max:2000']]);
        $this->service->update($data['settings']);
        return response()->json(['success' => true, 'message' => 'Đã lưu cấu hình hóa đơn.', 'data' => $this->service->forUi()]);
    }
}
