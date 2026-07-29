<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_upload_preview_search_delete_and_restore(): void
    {
        Storage::fake('local');
        $permission = Permission::where('code', 'documents.manage')->firstOrFail();
        $role = Role::create(['name'=>'Văn thư','code'=>'CLERK']);$role->permissions()->attach($permission);
        $user = User::factory()->create(['is_active'=>true]);$user->roles()->attach($role);
        $token = $user->createToken('document-test')->plainTextToken;

        $categoryId = $this->withToken($token)->postJson('/api/v1/documents/categories', [
            'code'=>'QUYET-DINH','name'=>'Quyết định','is_active'=>true,
        ])->assertCreated()->json('data.id');
        $documentId = $this->withToken($token)->post('/api/v1/documents', [
            'document_category_id'=>$categoryId,'code'=>'QD-001','name'=>'Quyết định thử nghiệm',
            'document_date'=>'2026-07-29','is_active'=>1,'file'=>UploadedFile::fake()->image('quyet-dinh.jpg',800,600),
        ])->assertCreated()->assertJsonPath('data.creator.id',$user->id)->json('data.id');

        $this->withToken($token)->getJson('/api/v1/documents?search=QD-001')->assertOk()->assertJsonPath('data.data.0.id',$documentId);
        $this->withToken($token)->get('/api/v1/documents/'.$documentId.'/preview')->assertOk()->assertHeader('content-type','image/jpeg');
        $this->withToken($token)->get('/api/v1/documents/'.$documentId.'/download')->assertOk()->assertDownload('quyet-dinh.jpg');
        $this->withToken($token)->deleteJson('/api/v1/documents/'.$documentId)->assertOk();
        $this->assertSoftDeleted('documents',['id'=>$documentId]);
        $this->withToken($token)->getJson('/api/v1/documents?with_deleted=1')->assertOk()->assertJsonPath('data.data.0.deleted_at',fn($value)=>$value!==null);
        $this->withToken($token)->postJson('/api/v1/documents/'.$documentId.'/restore')->assertOk();
        $this->assertDatabaseHas('documents',['id'=>$documentId,'deleted_at'=>null]);

    }

    public function test_viewer_can_read_but_cannot_manage_documents(): void
    {
        Storage::fake('local');Storage::disk('local')->put('documents/view.jpg','image');
        $category=\App\Models\DocumentCategory::create(['code'=>'VB','name'=>'Văn bản','is_active'=>true]);
        $document=\App\Models\Document::create(['document_category_id'=>$category->id,'code'=>'VB-01','name'=>'Văn bản xem','document_date'=>'2026-07-29','file_path'=>'documents/view.jpg','original_name'=>'view.jpg','extension'=>'jpg','mime_type'=>'image/jpeg','file_size'=>5,'is_active'=>true]);
        $permission=Permission::where('code','documents.view')->firstOrFail();$role=Role::create(['name'=>'Người xem','code'=>'VIEWER']);$role->permissions()->attach($permission);
        $viewer=User::factory()->create(['is_active'=>true]);$viewer->roles()->attach($role);$token=$viewer->createToken('viewer')->plainTextToken;
        $this->withToken($token)->getJson('/api/v1/documents')->assertOk()->assertJsonPath('data.data.0.id',$document->id);
        $this->withToken($token)->get('/api/v1/documents/'.$document->id.'/preview')->assertOk();
        $this->withToken($token)->deleteJson('/api/v1/documents/'.$document->id)->assertForbidden();
        $this->withToken($token)->post('/api/v1/documents',[])->assertForbidden();
    }
}
