<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('it_servers', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 40)->index();
            $table->string('name');
            $table->string('status', 32)->default('running')->index();
            $table->string('environment', 32)->nullable();
            $table->string('location')->nullable();
            $table->string('hostname')->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->string('operating_system')->nullable();
            $table->text('hosts')->nullable();
            $table->text('running_details')->nullable();
            $table->string('owner_name')->nullable();
            $table->text('notes')->nullable();
            $table->json('details')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('it_servers');
    }
};
