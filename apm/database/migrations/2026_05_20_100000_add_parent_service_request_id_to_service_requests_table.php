<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('service_requests', 'parent_service_request_id')) {
                $table->unsignedBigInteger('parent_service_request_id')->nullable()->after('id');
                $table->foreign('parent_service_request_id')
                    ->references('id')
                    ->on('service_requests')
                    ->nullOnDelete();
                $table->index('parent_service_request_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            if (Schema::hasColumn('service_requests', 'parent_service_request_id')) {
                $table->dropForeign(['parent_service_request_id']);
                $table->dropIndex(['parent_service_request_id']);
                $table->dropColumn('parent_service_request_id');
            }
        });
    }
};
