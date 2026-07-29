<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now=now();
        $leaderId=DB::table('roles')->where('code','LEADER')->value('id');
        if(!$leaderId)$leaderId=DB::table('roles')->insertGetId(['name'=>'Lãnh đạo','code'=>'LEADER','description'=>'Lãnh đạo đơn vị - toàn quyền nghiệp vụ, menu theo phân công','created_at'=>$now,'updated_at'=>$now]);
        $roleIds=DB::table('roles')->whereIn('code',['ADMIN','ACCOUNTANT','LEADER'])->pluck('id');
        $permissionIds=DB::table('permissions')->whereNull('deleted_at')->pluck('id');
        foreach($roleIds as $roleId)foreach($permissionIds as $permissionId)DB::table('permission_role')->insertOrIgnore(['permission_id'=>$permissionId,'role_id'=>$roleId]);
        $accountantUserIds=DB::table('role_user')->join('roles','roles.id','=','role_user.role_id')->where('roles.code','ACCOUNTANT')->pluck('role_user.user_id');
        $defaultMenuIds=DB::table('menus')->whereIn('path',['/','/households','/payments','/invoices','/debts','/reports','/inventory','/documents'])->pluck('id');
        foreach($accountantUserIds as $userId){DB::table('users')->where('id',$userId)->update(['menu_access_custom'=>true]);if(!DB::table('menu_user')->where('user_id',$userId)->exists())foreach($defaultMenuIds as $menuId)DB::table('menu_user')->insertOrIgnore(['user_id'=>$userId,'menu_id'=>$menuId,'created_at'=>$now,'updated_at'=>$now]);}
    }

    public function down(): void
    {
        $leader=DB::table('roles')->where('code','LEADER')->first();if($leader){DB::table('permission_role')->where('role_id',$leader->id)->delete();DB::table('role_user')->where('role_id',$leader->id)->delete();DB::table('roles')->where('id',$leader->id)->delete();}
    }
};
