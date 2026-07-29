<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissionId = DB::table('permissions')->where('code','documents.view')->value('id');
        if (! $permissionId) $permissionId = DB::table('permissions')->insertGetId(['name'=>'Xem văn bản, tài liệu','code'=>'documents.view','module'=>'documents','created_at'=>now(),'updated_at'=>now()]);
        foreach (DB::table('roles')->pluck('id') as $roleId) DB::table('permission_role')->insertOrIgnore(['permission_id'=>$permissionId,'role_id'=>$roleId]);
        DB::table('menus')->where('path','/documents')->update(['permission_code'=>'documents.view','updated_at'=>now()]);
    }

    public function down(): void
    {
        DB::table('menus')->where('path','/documents')->update(['permission_code'=>'documents.manage','updated_at'=>now()]);
        $ids=DB::table('permissions')->where('code','documents.view')->pluck('id');
        DB::table('permission_role')->whereIn('permission_id',$ids)->delete();
        DB::table('permissions')->whereIn('id',$ids)->delete();
    }
};
