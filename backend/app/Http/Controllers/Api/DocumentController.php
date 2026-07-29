<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\DocumentCategory;
use DOMDocument;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Html;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use ZipArchive;

class DocumentController extends Controller
{
    private function allowView(Request $request): void { abort_unless($request->user()->hasPermission('documents.view') || $request->user()->hasPermission('documents.manage'),403); }
    private function allowManage(Request $request): void { abort_unless($request->user()->hasPermission('documents.manage'),403); }

    public function options(Request $request): JsonResponse { $this->allowView($request);return response()->json(['success'=>true,'message'=>'Thành công','data'=>['categories'=>DocumentCategory::where('is_active',true)->orderBy('name')->get(['id','code','name'])]]); }
    public function categories(Request $request): JsonResponse { $this->allowManage($request);$q=DocumentCategory::query();if($request->boolean('with_deleted'))$q->withTrashed();if($request->filled('search')){$s='%'.trim($request->search).'%';$q->where(fn($x)=>$x->where('code','like',$s)->orWhere('name','like',$s));}return $this->ok($q->latest()->paginate(50)); }
    public function storeCategory(Request $request): JsonResponse { $this->allowManage($request);$item=DocumentCategory::create($this->categoryData($request));$this->audit($request,'CREATE',$item);return $this->created($item,'Đã thêm loại tài liệu.'); }
    public function updateCategory(Request $request, DocumentCategory $category): JsonResponse { $this->allowManage($request);$old=$category->toArray();$category->update($this->categoryData($request,$category->id));$this->audit($request,'UPDATE',$category,$old);return $this->ok($category,'Đã cập nhật loại tài liệu.'); }
    public function destroyCategory(Request $request, DocumentCategory $category): JsonResponse { $this->allowManage($request);abort_if($category->documents()->exists(),422,'Loại tài liệu đang được sử dụng, không thể xóa.');$this->audit($request,'DELETE',$category,$category->toArray());$category->delete();return $this->ok(null,'Đã xóa loại tài liệu.'); }
    public function restoreCategory(Request $request,int $id): JsonResponse { $this->allowManage($request);$item=DocumentCategory::withTrashed()->findOrFail($id);$item->restore();$this->audit($request,'RESTORE',$item);return $this->ok($item,'Đã khôi phục loại tài liệu.'); }
    private function categoryData(Request $r,?int $id=null):array{return $r->validate(['code'=>['required','max:50',Rule::unique('document_categories')->ignore($id)],'name'=>'required|max:255','is_active'=>'required|boolean']);}

    public function index(Request $request): JsonResponse
    {
        $this->allowView($request);$q=Document::with(['category:id,code,name','creator:id,name']);if($request->boolean('with_deleted') && $request->user()->hasPermission('documents.manage'))$q->withTrashed();
        if($request->filled('search')){$s='%'.trim($request->search).'%';$q->where(fn($x)=>$x->whereAny(['code','name','original_name','description'],'like',$s)->orWhereHas('category',fn($c)=>$c->where('name','like',$s)));}
        if($request->filled('category_id'))$q->where('document_category_id',$request->integer('category_id'));if($request->filled('is_active'))$q->where('is_active',$request->boolean('is_active'));
        if($request->filled('from_date'))$q->whereDate('document_date','>=',$request->from_date);if($request->filled('to_date'))$q->whereDate('document_date','<=',$request->to_date);
        return $this->ok($q->latest('document_date')->latest('id')->paginate(min($request->integer('per_page',30),100)));
    }
    public function store(Request $request): JsonResponse { $this->allowManage($request);$data=$this->documentData($request);$file=$request->file('file');$data=[...$data,...$this->storeFile($file),'created_by'=>$request->user()->id];$item=Document::create($data);$this->audit($request,'CREATE_DOCUMENT',$item);return $this->created($item->load(['category','creator']),'Đã thêm tài liệu.'); }
    public function update(Request $request, Document $document): JsonResponse { $this->allowManage($request);$old=$document->toArray();$data=$this->documentData($request,$document->id,true);if($request->hasFile('file')){$new=$this->storeFile($request->file('file'));Storage::disk('local')->delete($document->file_path);$data=[...$data,...$new];}$document->update($data);$this->audit($request,'UPDATE_DOCUMENT',$document,$old);return $this->ok($document->load(['category','creator']),'Đã cập nhật tài liệu.'); }
    public function destroy(Request $request, Document $document): JsonResponse { $this->allowManage($request);$this->audit($request,'DELETE_DOCUMENT',$document,$document->toArray());$document->delete();return $this->ok(null,'Đã xóa tài liệu. Có thể khôi phục lại.'); }
    public function restore(Request $request,int $id): JsonResponse { $this->allowManage($request);$document=Document::withTrashed()->findOrFail($id);$document->restore();$this->audit($request,'RESTORE_DOCUMENT',$document);return $this->ok($document->load(['category','creator']),'Đã khôi phục tài liệu.'); }
    private function documentData(Request $r,?int $id=null,bool $updating=false):array{return $r->validate(['document_category_id'=>'required|exists:document_categories,id','code'=>['required','max:80',Rule::unique('documents')->ignore($id)],'name'=>'required|max:255','document_date'=>'required|date','description'=>'nullable|string','is_active'=>'required|boolean','file'=>[$updating?'nullable':'required','file','mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx','max:20480']]);}
    private function storeFile($file):array{$extension=strtolower($file->getClientOriginalExtension());return ['file_path'=>$file->store('documents/'.now()->format('Y/m'),'local'),'original_name'=>$file->getClientOriginalName(),'extension'=>$extension,'mime_type'=>$file->getMimeType(),'file_size'=>$file->getSize()];}

    public function download(Request $request,int $id): BinaryFileResponse { $this->allowView($request);$document=Document::query()->when($request->user()->hasPermission('documents.manage'),fn($q)=>$q->withTrashed())->findOrFail($id);abort_unless(Storage::disk('local')->exists($document->file_path),404,'File tài liệu không còn tồn tại.');return response()->download(Storage::disk('local')->path($document->file_path),$document->original_name); }
    public function preview(Request $request,int $id): Response
    {
        $this->allowView($request);$document=Document::query()->when($request->user()->hasPermission('documents.manage'),fn($q)=>$q->withTrashed())->findOrFail($id);$disk=Storage::disk('local');abort_unless($disk->exists($document->file_path),404,'File tài liệu không còn tồn tại.');$path=$disk->path($document->file_path);$ext=$document->extension;
        if(in_array($ext,['pdf','jpg','jpeg','png','webp']))return response()->file($path,['Content-Type'=>$document->mime_type,'Content-Disposition'=>'inline; filename="'.addslashes($document->original_name).'"']);
        if(in_array($ext,['xls','xlsx']))return response($this->excelHtml($path,$document->name),200,['Content-Type'=>'text/html; charset=UTF-8','Content-Security-Policy'=>"default-src 'none'; style-src 'unsafe-inline'"]);
        if($ext==='docx')return response($this->docxHtml($path,$document->name),200,['Content-Type'=>'text/html; charset=UTF-8','Content-Security-Policy'=>"default-src 'none'; style-src 'unsafe-inline'"]);
        abort(422,'Định dạng Word .doc cũ chưa hỗ trợ xem trực tiếp. Vui lòng tải file xuống để xem.');
    }
    private function excelHtml(string $path,string $title):string{$sheet=IOFactory::load($path);$writer=new Html($sheet);$writer->writeAllSheets();ob_start();$writer->save('php://output');$content=ob_get_clean();return '<!doctype html><html><head><meta charset="utf-8"><title>'.e($title).'</title><style>body{font-family:Arial,sans-serif;padding:20px;color:#202428}table{border-collapse:collapse;margin-bottom:28px}td,th{border:1px solid #cfd5d2;padding:7px;min-width:60px}h1{font-size:20px}</style></head><body><h1>'.e($title).'</h1>'.$content.'</body></html>';}
    private function docxHtml(string $path,string $title):string{$zip=new ZipArchive();abort_unless($zip->open($path)===true,422,'Không thể đọc file DOCX.');$xml=$zip->getFromName('word/document.xml');$zip->close();abort_unless($xml,422,'File DOCX không có nội dung hợp lệ.');$dom=new DOMDocument();$dom->loadXML($xml,LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING);$paragraphs=[];foreach($dom->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main','p') as $p){$text='';foreach($p->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main','t') as $t)$text.=$t->textContent;if(trim($text)!=='')$paragraphs[]='<p>'.e($text).'</p>';}return '<!doctype html><html><head><meta charset="utf-8"><title>'.e($title).'</title><style>body{max-width:900px;margin:auto;padding:42px;font:16px/1.7 Arial,sans-serif;color:#202428;background:white}h1{font-size:24px;border-bottom:1px solid #ddd;padding-bottom:14px}p{white-space:pre-wrap}</style></head><body><h1>'.e($title).'</h1>'.implode('',$paragraphs).'</body></html>';}
    private function audit(Request $r,string $action,Model $item,?array $old=null):void{AuditLog::create(['user_id'=>$r->user()->id,'action'=>$action,'entity_type'=>$item::class,'entity_id'=>$item->getKey(),'old_values'=>$old,'new_values'=>str_contains($action,'DELETE')?null:$item->toArray(),'ip_address'=>$r->ip()]);}
    private function ok($data,string $message='Thành công'):JsonResponse{return response()->json(['success'=>true,'message'=>$message,'data'=>$data]);}private function created($data,string $message):JsonResponse{return response()->json(['success'=>true,'message'=>$message,'data'=>$data],201);}
}
