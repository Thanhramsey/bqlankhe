<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('code', 80)->unique();
            $table->string('name');
            $table->date('document_date');
            $table->string('file_path');
            $table->string('original_name');
            $table->string('extension', 10);
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        $permissionId = DB::table('permissions')->where('code', 'documents.manage')->value('id');
        if (! $permissionId) $permissionId = DB::table('permissions')->insertGetId(['name'=>'Quản lý văn bản, tài liệu','code'=>'documents.manage','module'=>'documents','created_at'=>now(),'updated_at'=>now()]);
        $adminIds = DB::table('roles')->where('code', 'ADMIN')->pluck('id');
        foreach ($adminIds as $roleId) DB::table('permission_role')->insertOrIgnore(['permission_id'=>$permissionId,'role_id'=>$roleId]);
        DB::table('menus')->updateOrInsert(['path'=>'#documents'], ['name'=>'Văn bản và tài liệu','icon'=>'file-document-multiple-outline','permission_code'=>null,'sort_order'=>35,'is_active'=>true,'created_at'=>now(),'updated_at'=>now()]);
        $parentId = DB::table('menus')->where('path','#documents')->value('id');
        DB::table('menus')->updateOrInsert(['path'=>'/documents'], ['parent_id'=>$parentId,'name'=>'Quản lý tài liệu','icon'=>'file-document-outline','permission_code'=>'documents.manage','sort_order'=>36,'is_active'=>true,'created_at'=>now(),'updated_at'=>now()]);
    }

    public function down(): void
    {
        DB::table('menus')->where('path','/documents')->delete();
        DB::table('menus')->where('path','#documents')->delete();
        $ids=DB::table('permissions')->where('code','documents.manage')->pluck('id');DB::table('permission_role')->whereIn('permission_id',$ids)->delete();DB::table('permissions')->whereIn('id',$ids)->delete();
        Schema::dropIfExists('documents');Schema::dropIfExists('document_categories');
    }
};
