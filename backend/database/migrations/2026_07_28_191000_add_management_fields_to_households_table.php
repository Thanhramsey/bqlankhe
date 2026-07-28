<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('households', function (Blueprint $table) {
            $table->string('identity_number', 20)->nullable()->unique()->after('phone');
            $table->string('email')->nullable()->after('identity_number');
            $table->unsignedInteger('sequence_number')->nullable()->after('code');
            $table->string('tax_code', 30)->nullable()->unique()->after('email');
            $table->string('representative')->nullable()->after('tax_code');
        });
    }

    public function down(): void
    {
        Schema::table('households', function (Blueprint $table) {
            $table->dropUnique(['identity_number']);
            $table->dropUnique(['tax_code']);
            $table->dropColumn(['identity_number', 'email', 'sequence_number', 'tax_code', 'representative']);
        });
    }
};
