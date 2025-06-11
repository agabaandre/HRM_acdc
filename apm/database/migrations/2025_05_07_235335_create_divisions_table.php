<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class RecreateDivisionsTable extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('budgets');
        DB::unprepared("ALTER TABLE `service_requests` DROP INDEX `service_requests_activity_id_foreign`");
        Schema::dropIfExists('divisions');

        Schema::create('divisions', function (Blueprint $table) {
            $table->bigIncrements('division_id');
            $table->string('division_name', 150);
            $table->unsignedBigInteger('division_head');
            $table->unsignedBigInteger('focal_person');
            $table->unsignedBigInteger('admin_assistant');
            $table->unsignedBigInteger('finance_officer');
            $table->unsignedBigInteger('directorate_id')->nullable();
            $table->unsignedBigInteger('head_oic_id')->nullable();
            $table->date('head_oic_start_date')->nullable();
            $table->date('head_oic_end_date')->nullable();
            $table->unsignedBigInteger('director_id')->nullable();
            $table->unsignedBigInteger('director_oic_id')->nullable();
            $table->date('director_oic_start_date')->nullable();
            $table->date('director_oic_end_date')->nullable();
            $table->enum('category', ['Programs', 'Operations', 'Other', ''])->default('Programs');
        });

        // Insert full SQL dump
        DB::unprepared("
            INSERT INTO `divisions` (`division_id`, `division_name`, `division_head`, `focal_person`, `admin_assistant`, `finance_officer`, `directorate_id`, `head_oic_id`, `head_oic_start_date`, `head_oic_end_date`, `director_id`, `director_oic_id`, `director_oic_start_date`, `director_oic_end_date`, `category`) VALUES
            (1, 'Central RCC', 456, 456, 456, 456, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Programs'),
            (2, 'Office of the Deputy Director General', 456, 205, 456, 16, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Programs'),
            (3, 'Office of the Director General', 456, 16, 105, 456, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Programs'),
            (4, 'Disease Control and Prevention', 1, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Programs'),
            (5, 'Eastern RCC', 1, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Programs'),
            (6, 'Emergency Preparedness and Response', 1, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Programs'),
            (7, 'Laboratory Networks and Systems', 1, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Programs'),
            (8, 'Directorate of Administration ', 1, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Programs'),
            (9, 'Northern RCC', 1, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Programs'),
            (10, 'Public Health Institutes and Research', 1, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Programs'),
            (11, 'Policy and Health Diplomacy', 1, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Programs'),
            (12, 'Directorate of Science and Innovation', 1, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Programs'),
            (13, 'Southern RCC', 1, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Programs'),
            (14, 'Surveillance and Disease Intelligence ', 1, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Programs'),
            (15, 'Western RCC', 1, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Programs'),
            (16, 'TBD', 1, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Programs'),
            (17, 'Executive Office', 1, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Programs'),
            (18, 'Directorate of Communication and Public Information', 1, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Programs'),
            (19, 'Directorate of Finance', 1, 1, 1, 1, 4, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Programs'),
            (20, 'Partnerships & Grants Management - Dissolved', 1, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Programs'),
            (21, 'Digital Health and Information Systems', 412, 74, 534, 268, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Programs'),
            (22, 'Planning Reporting and Accountability', 1, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Programs'),
            (23, 'Health Economics and Financing', 1, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Programs'),
            (24, 'Directorate of External Relations and Strategic Engagements', 1, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Programs'),
            (25, 'Legal Affairs and Dispute Settlement', 1, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Programs'),
            (26, 'Centre for Primary Healthcare', 1, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Programs'),
            (27, 'IMST - External', 1, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Programs'),
            (29, 'Local Manufacturing of Health Commodities', 1, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Programs'),
            (31, 'PIU', 1, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Programs'),
            (33, 'Supply Chain Management', 8, 188, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Programs'),
            (34, 'Human Resource Management', 1, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Programs'),
            (35, 'Community Health', 74, 74, 74, 74, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '');
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('divisions');
    }
}