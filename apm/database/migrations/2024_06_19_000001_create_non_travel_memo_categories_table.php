<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Schema::create('non_travel_memo_categories', function (Blueprint $table) {
        //     $table->id();
        //     $table->string('name');
        //     $table->timestamps();
        // });
    }

    public function down()
    {
       // Schema::dropIfExists('non_travel_memo_categories');
    }
};
