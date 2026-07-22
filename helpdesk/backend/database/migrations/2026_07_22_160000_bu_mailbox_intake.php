<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('helpdesk_business_units', function (Blueprint $table) {
            if (! Schema::hasColumn('helpdesk_business_units', 'support_mailbox')) {
                $table->string('support_mailbox', 191)->nullable()->after('allows_asset_link_on_resolve');
            }
            if (! Schema::hasColumn('helpdesk_business_units', 'email_intake_enabled')) {
                $table->boolean('email_intake_enabled')->default(false)->after('support_mailbox');
            }
        });

        DB::table('helpdesk_business_units')
            ->where('slug', 'it-mis')
            ->update([
                'support_mailbox' => 'helpdesk@africacdc.org',
                'email_intake_enabled' => true,
                'updated_at' => now(),
            ]);

        if (! Schema::hasTable('helpdesk_email_messages')) {
            Schema::create('helpdesk_email_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('business_unit_id')->constrained('helpdesk_business_units')->cascadeOnDelete();
                $table->string('graph_message_id', 191)->unique();
                $table->string('internet_message_id', 512)->nullable()->index();
                $table->foreignId('ticket_id')->nullable()->constrained('helpdesk_tickets')->nullOnDelete();
                $table->string('from_email', 191)->nullable();
                $table->string('subject', 500)->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->json('raw_meta')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('helpdesk_email_messages');
        Schema::table('helpdesk_business_units', function (Blueprint $table) {
            if (Schema::hasColumn('helpdesk_business_units', 'email_intake_enabled')) {
                $table->dropColumn('email_intake_enabled');
            }
            if (Schema::hasColumn('helpdesk_business_units', 'support_mailbox')) {
                $table->dropColumn('support_mailbox');
            }
        });
    }
};
