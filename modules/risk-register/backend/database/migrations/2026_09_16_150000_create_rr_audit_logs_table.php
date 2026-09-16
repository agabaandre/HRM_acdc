<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rr_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('actor_staff_id')->nullable()->index();
            $table->string('action', 64)->index();
            $table->string('entity_type', 64)->index();
            $table->unsignedBigInteger('entity_id')->nullable()->index();
            $table->json('before_json')->nullable();
            $table->json('after_json')->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rr_audit_logs');
    }
};
