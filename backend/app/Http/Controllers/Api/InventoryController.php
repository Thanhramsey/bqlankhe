<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\StockTransaction;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Services\ExcelExportService;
use App\Services\InventoryService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class InventoryController extends Controller
{
    public function __construct(private readonly InventoryService $service) {}
    private function allow(Request $request): void { abort_unless($request->user()->hasPermission('inventory.manage'), 403); }

    public function options(Request $request): JsonResponse
    {
        $this->allow($request);
        return response()->json(['success'=>true,'message'=>'Thành công','data'=>[
            'categories'=>MaterialCategory::where('is_active',true)->orderBy('name')->get(['id','code','name']),
            'warehouses'=>Warehouse::where('is_active',true)->orderBy('name')->get(['id','code','name']),
            'materials'=>Material::where('is_active',true)->orderBy('name')->get(['id','code','name','unit','price']),
        ]]);
    }

    public function categories(Request $request): JsonResponse { $this->allow($request); return $this->listing(MaterialCategory::query(),$request); }
    public function warehouses(Request $request): JsonResponse { $this->allow($request); return $this->listing(Warehouse::query(),$request); }
    private function listing($query, Request $request): JsonResponse { if($request->filled('search')){$s='%'.trim($request->search).'%';$query->where(fn($q)=>$q->where('code','like',$s)->orWhere('name','like',$s));} return response()->json(['success'=>true,'message'=>'Thành công','data'=>$query->latest()->paginate(50)]); }

    public function storeCategory(Request $request): JsonResponse { $this->allow($request); $data=$this->validateMaster($request,'material_categories'); return $this->created($request,MaterialCategory::create($data)); }
    public function updateCategory(Request $request, MaterialCategory $category): JsonResponse { $this->allow($request); $old=$category->toArray();$category->update($this->validateMaster($request,'material_categories',$category->id));return $this->updated($request,$category,$old); }
    public function destroyCategory(Request $request, MaterialCategory $category): JsonResponse { $this->allow($request); if($category->materials()->exists()) abort(422,'Không thể xóa loại vật tư đang được sử dụng.'); return $this->deleted($request,$category); }
    public function storeWarehouse(Request $request): JsonResponse { $this->allow($request); $data=$this->validateMaster($request,'warehouses');return $this->created($request,Warehouse::create($data)); }
    public function updateWarehouse(Request $request, Warehouse $warehouse): JsonResponse { $this->allow($request);$old=$warehouse->toArray();$warehouse->update($this->validateMaster($request,'warehouses',$warehouse->id));return $this->updated($request,$warehouse,$old); }
    public function destroyWarehouse(Request $request, Warehouse $warehouse): JsonResponse { $this->allow($request);if($warehouse->stocks()->where('quantity','>',0)->exists())abort(422,'Kho vẫn còn tồn vật tư, không thể xóa.');return $this->deleted($request,$warehouse); }
    private function validateMaster(Request $r,string $table,?int $id=null):array{return $r->validate(['code'=>['required','max:50',Rule::unique($table)->ignore($id)],'name'=>'required|max:255','description'=>$table==='material_categories'?'nullable|string':'exclude','note'=>$table==='warehouses'?'nullable|string':'exclude','is_active'=>'required|boolean']);}

    public function materials(Request $request): JsonResponse
    {
        $this->allow($request);$q=Material::with(['category:id,code,name','creator:id,name','stocks.warehouse:id,code,name']);
        if($request->filled('search')){$s='%'.trim($request->search).'%';$q->where(fn($x)=>$x->where('code','like',$s)->orWhere('name','like',$s)->orWhere('supplier','like',$s));}
        if($request->filled('category_id'))$q->where('material_category_id',$request->integer('category_id'));
        return response()->json(['success'=>true,'message'=>'Thành công','data'=>$q->latest()->paginate(50)]);
    }

    public function storeMaterial(Request $request): JsonResponse { $this->allow($request);$data=$this->validateMaterial($request);$data['created_by']=$request->user()->id;if($request->hasFile('image'))$data['image_path']=$request->file('image')->store('inventory/materials','public');return $this->created($request,Material::create($data)->load(['category','creator','stocks.warehouse'])); }
    public function updateMaterial(Request $request, Material $material): JsonResponse { $this->allow($request);$old=$material->toArray();$data=$this->validateMaterial($request,$material->id);if($request->hasFile('image')){if($material->image_path)Storage::disk('public')->delete($material->image_path);$data['image_path']=$request->file('image')->store('inventory/materials','public');}$material->update($data);return $this->updated($request,$material->load(['category','creator','stocks.warehouse']),$old); }
    public function destroyMaterial(Request $request, Material $material): JsonResponse { $this->allow($request);if($material->stocks()->where('quantity','>',0)->exists())abort(422,'Vật tư vẫn còn tồn kho, không thể xóa.');return $this->deleted($request,$material); }
    private function validateMaterial(Request $r,?int $id=null):array{return $r->validate(['material_category_id'=>'required|exists:material_categories,id','code'=>['required','max:50',Rule::unique('materials')->ignore($id)],'name'=>'required|max:255','unit'=>'required|max:50','supplier'=>'nullable|max:255','price'=>'required|numeric|min:0','description'=>'nullable|string','is_active'=>'required|boolean','image'=>'nullable|image|max:5120']);}

    public function transactions(Request $request): JsonResponse
    {
        $this->allow($request);$q=StockTransaction::with(['warehouse:id,code,name','creator:id,name','items.material:id,code,name,unit','documents']);
        if($request->filled('type'))$q->where('type',$request->type);if($request->filled('warehouse_id'))$q->where('warehouse_id',$request->integer('warehouse_id'));
        if($request->filled('from_date'))$q->whereDate('transaction_date','>=',$request->from_date);if($request->filled('to_date'))$q->whereDate('transaction_date','<=',$request->to_date);
        if($request->filled('material_id'))$q->whereHas('items',fn($x)=>$x->where('material_id',$request->integer('material_id')));
        if($request->filled('search')){$s='%'.trim($request->search).'%';$q->where(fn($x)=>$x->where('code','like',$s)->orWhere('partner','like',$s)->orWhere('reference_no','like',$s));}
        return response()->json(['success'=>true,'message'=>'Thành công','data'=>$q->latest('transaction_date')->latest('id')->paginate(50)]);
    }

    public function storeTransaction(Request $request): JsonResponse
    {
        $this->allow($request);$payload=$request->input('payload');if(is_string($payload))$request->merge(json_decode($payload,true)?:[]);
        $data=$this->validateTransaction($request);
        return response()->json(['success'=>true,'message'=>$data['type']==='IN'?'Đã nhập kho.':'Đã xuất kho.','data'=>$this->service->createTransaction($data,$request)],201);
    }

    public function updateTransaction(Request $request, StockTransaction $transaction): JsonResponse
    {
        $this->allow($request);$payload=$request->input('payload');if(is_string($payload))$request->merge(json_decode($payload,true)?:[]);
        $data=$this->validateTransaction($request,$transaction->id);
        $updated=$this->service->updateTransaction($transaction,$data,$request);
        return response()->json(['success'=>true,'message'=>'Đã cập nhật phiếu kho.','data'=>$updated]);
    }

    public function destroyTransaction(Request $request, StockTransaction $transaction): JsonResponse
    {
        $this->allow($request);
        $this->service->deleteTransaction($transaction,$request);
        return response()->json(['success'=>true,'message'=>'Đã xóa phiếu kho.','data'=>null]);
    }

    public function stocks(Request $request): JsonResponse
    {
        $this->allow($request);$q=WarehouseStock::with(['warehouse:id,code,name','material.category:id,code,name']);
        if($request->filled('warehouse_id'))$q->where('warehouse_id',$request->integer('warehouse_id'));if($request->filled('category_id'))$q->whereHas('material',fn($x)=>$x->where('material_category_id',$request->integer('category_id')));
        if($request->filled('search')){$s='%'.trim($request->search).'%';$q->whereHas('material',fn($x)=>$x->where('code','like',$s)->orWhere('name','like',$s));}
        return response()->json(['success'=>true,'message'=>'Thành công','data'=>$q->orderBy('warehouse_id')->get()]);
    }

    public function report(Request $request): JsonResponse
    {
        $this->allow($request);$stocks=WarehouseStock::with(['warehouse:id,code,name','material:id,code,name,unit,price'])->get();$transactions=StockTransaction::where('status','COMPLETED');
        if($request->filled('from_date'))$transactions->whereDate('transaction_date','>=',$request->from_date);if($request->filled('to_date'))$transactions->whereDate('transaction_date','<=',$request->to_date);
        return response()->json(['success'=>true,'message'=>'Thành công','data'=>['summary'=>['materials'=>Material::where('is_active',true)->count(),'warehouses'=>Warehouse::where('is_active',true)->count(),'stock_quantity'=>(float)$stocks->sum('quantity'),'stock_value'=>(float)$stocks->sum(fn($s)=>(float)$s->quantity*(float)$s->average_price),'in_value'=>(float)(clone $transactions)->where('type','IN')->sum('total_amount'),'out_value'=>(float)(clone $transactions)->where('type','OUT')->sum('total_amount')],'stocks'=>$stocks]]);
    }

    public function export(Request $request, ExcelExportService $excel): BinaryFileResponse
    {
        $this->allow($request);$stocks=WarehouseStock::with(['warehouse','material.category'])->get();$rows=$stocks->map(fn($s,$i)=>[$i+1,$s->warehouse->code,$s->warehouse->name,$s->material->code,$s->material->name,$s->material->category->name,$s->material->unit,(float)$s->quantity,(float)$s->average_price,(float)$s->quantity*(float)$s->average_price])->all();$path=$excel->create('Báo cáo tồn kho',['STT','Mã kho','Kho','Mã vật tư','Vật tư','Loại','ĐVT','Số lượng tồn','Giá bình quân','Giá trị tồn'],$rows,[8,14,24,16,28,22,10,16,18,20]);return response()->download($path,'bao-cao-ton-kho-'.now()->format('Ymd-His').'.xlsx')->deleteFileAfterSend();
    }

    private function validateTransaction(Request $request, ?int $id = null): array
    {
        return $request->validate(
            [
                'code' => ['nullable', 'max:60', Rule::unique('stock_transactions', 'code')->ignore($id)],
                'type' => 'required|in:IN,OUT',
                'warehouse_id' => 'required|exists:warehouses,id',
                'transaction_date' => 'required|date',
                'partner' => 'nullable|max:255',
                'reference_no' => 'nullable|max:100',
                'note' => 'nullable|string',
                'items' => 'required|array|min:1',
                'items.*.material_id' => 'required|distinct|exists:materials,id',
                'items.*.quantity' => 'required|numeric|gt:0',
                'items.*.unit_price' => 'nullable|numeric|min:0',
                'items.*.note' => 'nullable|string',
                'documents' => 'nullable|array|max:10',
                'documents.*' => 'file|mimes:pdf,jpg,jpeg,png,xls,xlsx,doc,docx|max:10240',
            ],
            [
                'warehouse_id.required' => 'Vui lòng chọn kho.',
                'warehouse_id.exists' => 'Kho được chọn không hợp lệ.',
                'type.required' => 'Vui lòng chọn loại phiếu.',
                'transaction_date.required' => 'Vui lòng chọn ngày nhập/xuất.',
                'items.required' => 'Vui lòng thêm ít nhất một dòng vật tư.',
                'items.min' => 'Vui lòng thêm ít nhất một dòng vật tư.',
                'items.*.material_id.required' => 'Vui lòng chọn vật tư cho từng dòng.',
                'items.*.material_id.exists' => 'Vật tư được chọn không hợp lệ.',
                'items.*.quantity.required' => 'Vui lòng nhập số lượng.',
                'items.*.quantity.gt' => 'Số lượng phải lớn hơn 0.',
            ],
            [
                'warehouse_id' => 'kho',
                'type' => 'loại phiếu',
                'transaction_date' => 'ngày nhập/xuất',
                'items' => 'danh sách vật tư',
                'items.*.material_id' => 'vật tư',
                'items.*.quantity' => 'số lượng',
                'items.*.unit_price' => 'đơn giá',
            ],
        );
    }

    private function created(Request $r,Model $m):JsonResponse{$this->audit($r,'CREATE',$m);return response()->json(['success'=>true,'message'=>'Đã thêm dữ liệu.','data'=>$m],201);} private function updated(Request $r,Model $m,array $old):JsonResponse{$this->audit($r,'UPDATE',$m,$old);return response()->json(['success'=>true,'message'=>'Đã cập nhật dữ liệu.','data'=>$m]);} private function deleted(Request $r,Model $m):JsonResponse{$this->audit($r,'DELETE',$m,$m->toArray());$m->delete();return response()->json(['success'=>true,'message'=>'Đã xóa dữ liệu.','data'=>null]);}
    private function audit(Request $r,string $action,Model $m,?array $old=null):void{AuditLog::create(['user_id'=>$r->user()->id,'action'=>$action,'entity_type'=>$m::class,'entity_id'=>$m->getKey(),'old_values'=>$old,'new_values'=>$action==='DELETE'?null:$m->toArray(),'ip_address'=>$r->ip()]);}
}
