<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\OperatingDirective;
use App\Models\OperatingDirectiveAttachment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OperatingDirectiveController extends Controller
{
    private function canSend(Request $r):void{abort_unless($r->user()->hasPermission('directives.send'),403);}
    private function canReceive(Request $r):void{abort_unless($r->user()->hasPermission('directives.receive')||$r->user()->hasPermission('directives.send'),403);}
    public function options(Request $r):JsonResponse{$this->canSend($r);return $this->ok(['users'=>User::where('is_active',true)->whereKeyNot($r->user()->id)->orderBy('name')->get(['id','username','name','email','avatar'])]);}
    public function sent(Request $r):JsonResponse
    {
        $this->canSend($r);$q=OperatingDirective::with(['creator:id,name','recipients:id,name,email','attachments'])->withCount('recipients')->withCount(['recipients as read_count'=>fn($x)=>$x->whereNotNull('operating_directive_recipient.read_at')])->where('created_by',$r->user()->id);
        $this->filters($q,$r);return $this->ok($q->latest()->paginate(min($r->integer('per_page',30),100)));
    }
    public function inbox(Request $r):JsonResponse
    {
        $this->canReceive($r);$q=OperatingDirective::with(['creator:id,name,email','attachments'])->where('is_active',true)->whereHas('recipients',fn($x)=>$x->where('users.id',$r->user()->id))->with(['recipients'=>fn($x)=>$x->where('users.id',$r->user()->id)]);
        $this->filters($q,$r);if($r->filled('read_status'))$q->whereHas('recipients',fn($x)=>$x->where('users.id',$r->user()->id)->when($r->read_status==='unread',fn($y)=>$y->whereNull('operating_directive_recipient.read_at'),fn($y)=>$y->whereNotNull('operating_directive_recipient.read_at')));return $this->ok($q->latest()->paginate(min($r->integer('per_page',30),100)));
    }
    private function filters($q,Request $r):void{if($r->filled('search')){$s='%'.trim($r->search).'%';$q->where(fn($x)=>$x->where('code','like',$s)->orWhere('title','like',$s)->orWhere('content','like',$s));}if($r->filled('from_date'))$q->whereDate('created_at','>=',$r->from_date);if($r->filled('to_date'))$q->whereDate('created_at','<=',$r->to_date);}
    public function unread(Request $r):JsonResponse{$this->canReceive($r);$q=OperatingDirective::where('is_active',true)->whereHas('recipients',fn($x)=>$x->where('users.id',$r->user()->id)->whereNull('operating_directive_recipient.read_at'));return $this->ok(['count'=>$q->count(),'items'=>$q->with('creator:id,name')->latest()->take(5)->get(['id','created_by','code','title','created_at'])]);}
    public function store(Request $r):JsonResponse{$this->canSend($r);$data=$this->validated($r);$item=DB::transaction(function()use($r,$data){$item=OperatingDirective::create(['created_by'=>$r->user()->id,'code'=>$data['code']??$this->nextCode(),'title'=>$data['title'],'content'=>$this->sanitize($data['content']),'is_active'=>$data['is_active']]);$item->recipients()->sync($data['recipient_ids']);$this->saveFiles($item,$r->file('attachments',[]));return $item;});$this->audit($r,'CREATE_DIRECTIVE',$item);return $this->created($this->load($item),'Đã gửi thông tin điều hành.');}
    public function update(Request $r,OperatingDirective $directive):JsonResponse{$this->canSend($r);$this->owner($r,$directive);$old=$directive->load('recipients')->toArray();$data=$this->validated($r,$directive->id,true);DB::transaction(function()use($r,$directive,$data){$directive->update(['code'=>$data['code'],'title'=>$data['title'],'content'=>$this->sanitize($data['content']),'is_active'=>$data['is_active']]);$directive->recipients()->sync($data['recipient_ids']);if(!empty($data['remove_attachment_ids'])){$files=$directive->attachments()->whereIn('id',$data['remove_attachment_ids'])->get();foreach($files as $file){Storage::disk('local')->delete($file->file_path);$file->delete();}}$this->saveFiles($directive,$r->file('attachments',[]));});$this->audit($r,'UPDATE_DIRECTIVE',$directive,$old);return $this->ok($this->load($directive),'Đã cập nhật thông tin điều hành.');}
    public function destroy(Request $r,OperatingDirective $directive):JsonResponse{$this->canSend($r);$this->owner($r,$directive);$this->audit($r,'DELETE_DIRECTIVE',$directive,$directive->toArray());$directive->delete();return $this->ok(null,'Đã xóa thông tin điều hành.');}
    public function show(Request $r,int $id):JsonResponse
    {
        $this->canReceive($r);$directive=OperatingDirective::with(['creator:id,name,email,avatar','recipients:id,name,email,avatar','attachments'])->findOrFail($id);$isOwner=$directive->created_by===$r->user()->id;$recipient=$directive->recipients->firstWhere('id',$r->user()->id);abort_unless($isOwner||$recipient,403,'Bạn không nằm trong danh sách nhận thông tin này.');if($recipient&&!$recipient->pivot->read_at)$directive->recipients()->updateExistingPivot($r->user()->id,['read_at'=>now()]);return $this->ok($directive->fresh()->load(['creator:id,name,email,avatar','recipients:id,name,email,avatar','attachments']));
    }
    public function attachment(Request $r,OperatingDirectiveAttachment $attachment):BinaryFileResponse{$this->canReceive($r);$directive=$attachment->directive;$allowed=$directive->created_by===$r->user()->id||$directive->recipients()->where('users.id',$r->user()->id)->exists();abort_unless($allowed,403);abort_unless(Storage::disk('local')->exists($attachment->file_path),404);return response()->download(Storage::disk('local')->path($attachment->file_path),$attachment->original_name);}
    private function owner(Request $r,OperatingDirective $directive):void{abort_unless($directive->created_by===$r->user()->id,403,'Bạn chỉ được chỉnh sửa thông tin do mình tạo.');}
    private function validated(Request $r,?int $id=null,bool $updating=false):array{return $r->validate(['code'=>['nullable','max:60',Rule::unique('operating_directives')->ignore($id)],'title'=>'required|max:255','content'=>'required|string|max:100000','recipient_ids'=>'required|array|min:1','recipient_ids.*'=>'integer|distinct|exists:users,id','is_active'=>'required|boolean','attachments'=>'nullable|array|max:10','attachments.*'=>'file|mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx|max:20480','remove_attachment_ids'=>$updating?'nullable|array':'exclude','remove_attachment_ids.*'=>'integer|exists:operating_directive_attachments,id']);}
    private function saveFiles(OperatingDirective $item,array $files):void{foreach($files as $file){$ext=strtolower($file->getClientOriginalExtension());$item->attachments()->create(['original_name'=>$file->getClientOriginalName(),'file_path'=>$file->store('directives/'.now()->format('Y/m'),'local'),'mime_type'=>$file->getMimeType(),'extension'=>$ext,'file_size'=>$file->getSize()]);}}
    private function sanitize(string $html):string{$html=preg_replace('#<(script|style)[^>]*>.*?</\1>#is','',$html)??'';$html=strip_tags($html,'<p><br><strong><b><em><i><u><s><ul><ol><li><h1><h2><h3><blockquote><hr>');return preg_replace('/<([a-z0-9]+)\s+[^>]*>/i','<$1>',$html)??'';}
    private function nextCode():string{return 'DH'.now()->format('YmdHis').random_int(100,999);}private function load(OperatingDirective $item):OperatingDirective{return $item->load(['creator:id,name','recipients:id,name,email','attachments']);}
    private function audit(Request $r,string $action,OperatingDirective $item,?array $old=null):void{AuditLog::create(['user_id'=>$r->user()->id,'action'=>$action,'entity_type'=>OperatingDirective::class,'entity_id'=>$item->id,'old_values'=>$old,'new_values'=>str_contains($action,'DELETE')?null:$item->toArray(),'ip_address'=>$r->ip()]);}
    private function ok($data,string $message='Thành công'):JsonResponse{return response()->json(['success'=>true,'message'=>$message,'data'=>$data]);}private function created($data,string $message):JsonResponse{return response()->json(['success'=>true,'message'=>$message,'data'=>$data],201);}
}
