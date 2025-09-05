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
        Schema::table('funders', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name');
            $table->string('contact_person')->nullable()->after('description');
            $table->string('email')->nullable()->after('contact_person');
            $table->string('phone')->nullable()->after('email');
            $table->text('address')->nullable()->after('phone');
            $table->string('website')->nullable()->after('address');
            $table->boolean('is_active')->default(true)->after('website');
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('funders', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn([
                'description',
                'contact_person',
                'email',
                'phone',
                'address',
                'website',
                'is_active'
            ]);
        });
    }
};
