<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->string('service_title')->nullable()->change();
            $table->text('description')->nullable()->change();
            $table->text('justification')->nullable()->change();
            $table->date('required_by_date')->nullable()->change();
            $table->string('location')->nullable()->change();
            $table->decimal('estimated_cost', 15, 2)->nullable()->change();
            $table->string('priority')->nullable()->change();
            $table->string('service_type')->nullable()->change();
            $table->json('specifications')->nullable()->change();
            $table->json('attachments')->nullable()->change();
            $table->string('status')->nullable()->change();
            $table->text('remarks')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->string('service_title')->nullable(false)->change();
            $table->text('description')->nullable(false)->change();
            $table->text('justification')->nullable(false)->change();
            $table->date('required_by_date')->nullable(false)->change();
            $table->string('location')->nullable(false)->change();
            $table->decimal('estimated_cost', 15, 2)->nullable(false)->change();
            $table->string('priority')->nullable(false)->change();
            $table->string('service_type')->nullable(false)->change();
            $table->json('specifications')->nullable(false)->change();
            $table->json('attachments')->nullable(false)->change();
            $table->string('status')->nullable(false)->change();
            $table->text('remarks')->nullable(false)->change();
        });
    }
};
