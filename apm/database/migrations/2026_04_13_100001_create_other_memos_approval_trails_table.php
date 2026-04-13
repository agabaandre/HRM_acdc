<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('other_memos_approval_trails', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('other_memo_id')->index();
            $table->unsignedInteger('approval_order')->default(0);
            $table->unsignedBigInteger('staff_id')->index();
            $table->string('action', 64);
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('other_memos_approval_trails');
    }
};
