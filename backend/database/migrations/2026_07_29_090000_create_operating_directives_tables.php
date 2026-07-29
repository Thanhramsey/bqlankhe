<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operating_directives',function(Blueprint $table){$table->id();$table->foreignId('created_by')->constrained('users')->restrictOnDelete();$table->string('code',60)->unique();$table->string('title');$table->longText('content');$table->boolean('is_active')->default(true);$table->timestamps();$table->softDeletes();});
        Schema::create('operating_directive_recipient',function(Blueprint $table){$table->foreignId('operating_directive_id')->constrained()->cascadeOnDelete();$table->foreignId('user_id')->constrained()->cascadeOnDelete();$table->timestamp('read_at')->nullable();$table->timestamps();$table->primary(['operating_directive_id','user_id']);});
        Schema::create('operating_directive_attachments',function(Blueprint $table){$table->id();$table->foreignId('operating_directive_id')->constrained()->cascadeOnDelete();$table->string('original_name');$table->string('file_path');$table->string('mime_type',120)->nullable();$table->string('extension',10);$table->unsignedBigInteger('file_size')->default(0);$table->timestamps();});

        $receiveId=DB::table('permissions')->insertGetId(['name'=>'Nhận thông tin điều hành','code'=>'directives.receive','module'=>'directives','created_at'=>now(),'updated_at'=>now()]);
        $sendId=DB::table('permissions')->insertGetId(['name'=>'Gửi thông tin điều hành','code'=>'directives.send','module'=>'directives','created_at'=>now(),'updated_at'=>now()]);
        foreach(DB::table('roles')->pluck('id') as $roleId)DB::table('permission_role')->insertOrIgnore(['permission_id'=>$receiveId,'role_id'=>$roleId]);
        foreach(DB::table('roles')->whereIn('code',['ADMIN','LEADER','ACCOUNTANT'])->pluck('id') as $roleId)DB::table('permission_role')->insertOrIgnore(['permission_id'=>$sendId,'role_id'=>$roleId]);
        DB::table('menus')->updateOrInsert(['path'=>'#directives'],['name'=>'Thông tin điều hành','icon'=>'bullhorn-outline','permission_code'=>null,'sort_order'=>32,'is_active'=>true,'created_at'=>now(),'updated_at'=>now()]);
        $parentId=DB::table('menus')->where('path','#directives')->value('id');
        DB::table('menus')->updateOrInsert(['path'=>'/directives/sent'],['parent_id'=>$parentId,'name'=>'Gửi thông tin điều hành','icon'=>'send-outline','permission_code'=>'directives.send','sort_order'=>33,'is_active'=>true,'created_at'=>now(),'updated_at'=>now()]);
        DB::table('menus')->updateOrInsert(['path'=>'/directives/inbox'],['parent_id'=>$parentId,'name'=>'Thông tin điều hành nhận','icon'=>'inbox-arrow-down-outline','permission_code'=>'directives.receive','sort_order'=>34,'is_active'=>true,'created_at'=>now(),'updated_at'=>now()]);
        $customUsers=DB::table('users')->where('menu_access_custom',true)->pluck('id');$inboxId=DB::table('menus')->where('path','/directives/inbox')->value('id');
        foreach($customUsers as $userId)DB::table('menu_user')->insertOrIgnore(['user_id'=>$userId,'menu_id'=>$inboxId,'created_at'=>now(),'updated_at'=>now()]);
    }
    public function down():void{$menuIds=DB::table('menus')->whereIn('path',['/directives/sent','/directives/inbox','#directives'])->pluck('id');DB::table('menu_user')->whereIn('menu_id',$menuIds)->delete();DB::table('menus')->whereIn('id',$menuIds)->delete();$permissionIds=DB::table('permissions')->whereIn('code',['directives.receive','directives.send'])->pluck('id');DB::table('permission_role')->whereIn('permission_id',$permissionIds)->delete();DB::table('permissions')->whereIn('id',$permissionIds)->delete();Schema::dropIfExists('operating_directive_attachments');Schema::dropIfExists('operating_directive_recipient');Schema::dropIfExists('operating_directives');}
};
