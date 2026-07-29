<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\HouseholdExcelService;
use App\Models\AuditLog;
use App\Models\Household;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class HouseholdExcelController extends Controller
{
    public function __construct(private readonly HouseholdExcelService $service) {}

    public function template(Request $request): BinaryFileResponse
    {
        abort_unless($request->user()->hasPermission('households.manage'), 403);
        return response()->download($this->service->template(), 'mau-import-ho-dan.xlsx')->deleteFileAfterSend();
    }

    public function import(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('households.manage'), 403);
        $request->validate(['file' => 'required|file|mimes:xlsx,xls|max:10240']);
        $result = $this->service->import($request->file('file'));
        AuditLog::create(['user_id' => $request->user()->id, 'action' => 'IMPORT_HOUSEHOLDS', 'entity_type' => Household::class, 'new_values' => ['file_name' => $request->file('file')->getClientOriginalName(), ...$result], 'ip_address' => $request->ip()]);
        return response()->json(['success' => true, 'message' => 'Import hộ dân thành công.', 'data' => $result]);
    }
}
