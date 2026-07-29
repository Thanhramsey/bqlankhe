<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('issued_by')->nullable()->after('issued_at')->constrained('users')->nullOnDelete();
        });

        DB::table('audit_logs')->where('action', 'PUBLISH_INVOICE')
            ->where('entity_type', 'App\\Models\\Invoice')->whereNotNull('entity_id')->whereNotNull('user_id')
            ->orderBy('id')->get(['entity_id', 'user_id'])->unique('entity_id')
            ->each(fn ($log) => DB::table('invoices')->where('id', $log->entity_id)->update(['issued_by' => $log->user_id]));
    }

    public function down(): void
    {
        Schema::table('invoices', fn (Blueprint $table) => $table->dropConstrainedForeignId('issued_by'));
    }
};
