<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Services\RouteExcelService;
use App\Models\AuditLog;
use App\Models\CollectionRoute;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
class RouteExcelController extends Controller
{
    public function __construct(private readonly RouteExcelService $service) {}
    public function template(Request $request): BinaryFileResponse { abort_unless($request->user()->hasPermission('routes.manage'),403); return response()->download($this->service->template(),'mau-import-tuyen-thu.xlsx')->deleteFileAfterSend(); }
    public function import(Request $request): JsonResponse { abort_unless($request->user()->hasPermission('routes.manage'),403); $request->validate(['file'=>'required|file|mimes:xlsx,xls|max:10240']); $counts=$this->service->import($request->file('file')); AuditLog::create(['user_id'=>$request->user()->id,'action'=>'IMPORT_ROUTES','entity_type'=>CollectionRoute::class,'new_values'=>['file_name'=>$request->file('file')->getClientOriginalName(),...$counts],'ip_address'=>$request->ip()]); return response()->json(['success'=>true,'message'=>'Import dữ liệu thành công.','data'=>$counts]); }
}
