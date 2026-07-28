<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collection_route_user', function (Blueprint $table) {
            $table->foreignId('collection_route_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['collection_route_id', 'user_id']);
        });

        DB::table('users')->whereNotNull('collection_route_id')->orderBy('id')->each(function ($user) {
            DB::table('collection_route_user')->insertOrIgnore([
                'collection_route_id' => $user->collection_route_id,
                'user_id' => $user->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });
        Schema::dropIfExists('collection_route_employee');
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_route_user');
    }
};
