<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->after('email');
            $table->boolean('is_active')->default(true)->after('password');
            $table->softDeletes();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('module');
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('role_user', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->primary(['user_id', 'role_id']);
        });
        Schema::create('permission_role', function (Blueprint $table) {
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->primary(['permission_id', 'role_id']);
        });
        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('menus')->nullOnDelete();
            $table->string('name');
            $table->string('path');
            $table->string('icon')->nullable();
            $table->string('permission_code')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('collection_routes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('collection_route_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('phone', 20)->nullable();
            $table->string('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('households', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_route_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code')->unique();
            $table->string('owner_name');
            $table->string('phone', 20)->nullable();
            $table->string('address');
            $table->string('ward')->default('An Khê');
            $table->text('note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->decimal('monthly_price', 15, 2);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('household_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->decimal('monthly_price', 15, 2);
            $table->date('started_at');
            $table->date('ended_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('household_id')->constrained()->restrictOnDelete();
            $table->foreignId('collector_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('from_month');
            $table->date('to_month');
            $table->decimal('amount', 15, 2);
            $table->string('status')->default('DA_THU');
            $table->string('payment_method')->default('TIEN_MAT');
            $table->timestamp('paid_at');
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('payment_months', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('household_service_id')->constrained()->restrictOnDelete();
            $table->date('month');
            $table->decimal('amount', 15, 2);
            $table->timestamps();
            $table->unique(['household_service_id', 'month']);
        });
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->unique()->constrained()->restrictOnDelete();
            $table->string('provider')->default('VNPT');
            $table->string('invoice_no')->nullable()->unique();
            $table->string('status')->default('CHO_PHAT_HANH');
            $table->json('provider_response')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('debts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->date('month');
            $table->decimal('amount', 15, 2);
            $table->string('status')->default('CHUA_THU');
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['household_id', 'month']);
        });
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type')->default('string');
            $table->string('group')->default('general');
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('debts');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('payment_months');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('household_services');
        Schema::dropIfExists('services');
        Schema::dropIfExists('households');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('collection_routes');
        Schema::dropIfExists('menus');
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['phone', 'is_active', 'deleted_at']));
    }
};
