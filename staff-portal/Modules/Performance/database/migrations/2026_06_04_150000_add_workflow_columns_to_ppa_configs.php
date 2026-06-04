<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ppa_configs', function (Blueprint $table): void {
            $table->boolean('ppa_requires_second_supervisor')->default(false)->after('allow_employee_comments');
            $table->boolean('midterm_requires_second_supervisor')->default(false)->after('ppa_requires_second_supervisor');
            $table->boolean('endterm_requires_second_supervisor')->default(true)->after('midterm_requires_second_supervisor');
            $table->boolean('endterm_requires_employee_consent')->default(true)->after('endterm_requires_second_supervisor');
        });
    }

    public function down(): void
    {
        Schema::table('ppa_configs', function (Blueprint $table): void {
            $table->dropColumn([
                'ppa_requires_second_supervisor',
                'midterm_requires_second_supervisor',
                'endterm_requires_second_supervisor',
                'endterm_requires_employee_consent',
            ]);
        });
    }
};
