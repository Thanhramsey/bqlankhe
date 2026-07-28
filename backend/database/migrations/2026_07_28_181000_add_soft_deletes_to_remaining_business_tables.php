<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { foreach (['debts','payment_months','system_settings'] as $name) Schema::table($name, fn(Blueprint $table) => $table->softDeletes()); }
    public function down(): void { foreach (['debts','payment_months','system_settings'] as $name) Schema::table($name, fn(Blueprint $table) => $table->dropSoftDeletes()); }
};
