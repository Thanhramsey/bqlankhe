<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provinces', function (Blueprint $table) {
            $table->id(); $table->string('code', 20)->unique(); $table->string('name');
            $table->timestamps(); $table->softDeletes();
        });
        Schema::create('wards', function (Blueprint $table) {
            $table->id(); $table->foreignId('province_id')->constrained()->restrictOnDelete();
            $table->string('code', 20); $table->string('name'); $table->timestamps(); $table->softDeletes();
            $table->unique(['province_id', 'code']);
        });
        Schema::create('neighborhoods', function (Blueprint $table) {
            $table->id(); $table->foreignId('ward_id')->constrained()->restrictOnDelete();
            $table->string('code', 30); $table->string('name'); $table->timestamps(); $table->softDeletes();
            $table->unique(['ward_id', 'code']);
        });
        Schema::table('collection_routes', function (Blueprint $table) {
            $table->foreignId('neighborhood_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });
        Schema::create('collection_route_employee', function (Blueprint $table) {
            $table->foreignId('collection_route_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->timestamps(); $table->primary(['collection_route_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_route_employee');
        Schema::table('collection_routes', fn (Blueprint $table) => $table->dropConstrainedForeignId('neighborhood_id'));
        Schema::dropIfExists('neighborhoods'); Schema::dropIfExists('wards'); Schema::dropIfExists('provinces');
    }
};
