<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->whereNull('username')->orderBy('id')->each(function ($user) {
            $base = strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '', explode('@', $user->email)[0])) ?: 'user';
            $username = $base;
            if (DB::table('users')->where('username', $username)->where('id', '!=', $user->id)->exists()) {
                $username = $base.$user->id;
            }
            DB::table('users')->where('id', $user->id)->update(['username' => $username]);
        });
    }

    public function down(): void {}
};
