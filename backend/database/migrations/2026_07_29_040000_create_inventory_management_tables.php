<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->text('note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->string('unit', 50);
            $table->string('supplier')->nullable();
            $table->decimal('price', 18, 2)->default(0);
            $table->string('image_path')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('warehouse_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('material_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 18, 3)->default(0);
            $table->decimal('average_price', 18, 2)->default(0);
            $table->timestamps();
            $table->unique(['warehouse_id', 'material_id']);
        });

        Schema::create('stock_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('code', 60)->unique();
            $table->enum('type', ['IN', 'OUT']);
            $table->date('transaction_date');
            $table->string('partner')->nullable();
            $table->string('reference_no')->nullable();
            $table->enum('status', ['COMPLETED', 'CANCELLED'])->default('COMPLETED');
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('stock_transaction_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 18, 3);
            $table->decimal('unit_price', 18, 2)->default(0);
            $table->decimal('amount', 18, 2)->default(0);
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('original_name');
            $table->string('path');
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();
        });

        $permissionId = DB::table('permissions')->insertGetId([
            'name' => 'Quản lý vật tư', 'code' => 'inventory.manage', 'module' => 'inventory',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $adminIds = DB::table('roles')->where('code', 'ADMIN')->pluck('id');
        foreach ($adminIds as $roleId) DB::table('permission_role')->insertOrIgnore(['permission_id' => $permissionId, 'role_id' => $roleId]);
        DB::table('menus')->updateOrInsert(['path' => '/inventory'], [
            'name' => 'Quản lý vật tư', 'permission_code' => 'inventory.manage', 'sort_order' => 35,
            'icon' => 'package-variant-closed', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('menus')->where('path', '/inventory')->delete();
        $permissionIds = DB::table('permissions')->where('code', 'inventory.manage')->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        Schema::dropIfExists('stock_documents');
        Schema::dropIfExists('stock_transaction_items');
        Schema::dropIfExists('stock_transactions');
        Schema::dropIfExists('warehouse_stocks');
        Schema::dropIfExists('materials');
        Schema::dropIfExists('warehouses');
        Schema::dropIfExists('material_categories');
    }
};
