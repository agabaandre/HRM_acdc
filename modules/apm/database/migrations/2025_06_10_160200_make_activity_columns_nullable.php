<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MakeActivityColumnsNullable extends Migration
{
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->unsignedBigInteger('forward_workflow_id')->nullable()->change();
            $table->unsignedBigInteger('reverse_workflow_id')->nullable()->change();
            $table->string('workplan_activity_code', 255)->nullable()->change();
            $table->unsignedBigInteger('matrix_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->unsignedBigInteger('forward_workflow_id')->nullable(false)->change();
            $table->unsignedBigInteger('reverse_workflow_id')->nullable(false)->change();
            $table->string('workplan_activity_code', 255)->nullable(false)->change();
            $table->unsignedBigInteger('matrix_id')->nullable(false)->change();
        });
    }
}
