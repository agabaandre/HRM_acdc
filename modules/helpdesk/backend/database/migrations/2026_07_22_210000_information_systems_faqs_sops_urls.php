<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('helpdesk_information_systems')) {
            return;
        }

        Schema::table('helpdesk_information_systems', function (Blueprint $table) {
            if (Schema::hasColumn('helpdesk_information_systems', 'faqs')
                && ! Schema::hasColumn('helpdesk_information_systems', 'faqs_url')) {
                $table->renameColumn('faqs', 'faqs_url');
            }
            if (Schema::hasColumn('helpdesk_information_systems', 'sops')
                && ! Schema::hasColumn('helpdesk_information_systems', 'sops_url')) {
                $table->renameColumn('sops', 'sops_url');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('helpdesk_information_systems')) {
            return;
        }

        Schema::table('helpdesk_information_systems', function (Blueprint $table) {
            if (Schema::hasColumn('helpdesk_information_systems', 'faqs_url')
                && ! Schema::hasColumn('helpdesk_information_systems', 'faqs')) {
                $table->renameColumn('faqs_url', 'faqs');
            }
            if (Schema::hasColumn('helpdesk_information_systems', 'sops_url')
                && ! Schema::hasColumn('helpdesk_information_systems', 'sops')) {
                $table->renameColumn('sops_url', 'sops');
            }
        });
    }
};
