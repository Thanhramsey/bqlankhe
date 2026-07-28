<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\HouseholdExcelService;
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
        return response()->json(['success' => true, 'message' => 'Import hộ dân thành công.', 'data' => $this->service->import($request->file('file'))]);
    }
}
