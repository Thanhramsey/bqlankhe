<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 100)->nullable()->unique()->after('id');
            $table->date('date_of_birth')->nullable()->after('name');
            $table->string('gender', 20)->nullable()->after('date_of_birth');
            $table->string('identity_number', 30)->nullable()->unique()->after('phone');
            $table->string('address')->nullable()->after('identity_number');
            $table->string('avatar')->nullable()->after('address');
            $table->foreignId('collection_route_id')->nullable()->after('avatar')
                ->constrained('collection_routes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('collection_route_id');
            $table->dropUnique(['username']);
            $table->dropUnique(['identity_number']);
            $table->dropColumn([
                'username', 'date_of_birth', 'gender', 'identity_number', 'address', 'avatar',
            ]);
        });
    }
};
