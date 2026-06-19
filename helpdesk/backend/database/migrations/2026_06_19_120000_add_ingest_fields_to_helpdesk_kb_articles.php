<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('helpdesk_kb_articles', function (Blueprint $table) {
            $table->string('source', 64)->nullable()->after('is_active');
            $table->string('external_id', 191)->nullable()->after('source');
            $table->string('source_url', 512)->nullable()->after('external_id');
            $table->text('search_keywords')->nullable()->after('source_url');
            $table->timestamp('ingested_at')->nullable()->after('search_keywords');
            $table->string('content_hash', 64)->nullable()->after('ingested_at');

            $table->unique(['source', 'external_id'], 'kb_articles_source_external_unique');
            $table->index(['source', 'is_active'], 'kb_articles_source_active_idx');
        });
    }

    public function down(): void
    {
        Schema::table('helpdesk_kb_articles', function (Blueprint $table) {
            $table->dropUnique('kb_articles_source_external_unique');
            $table->dropIndex('kb_articles_source_active_idx');
            $table->dropColumn([
                'source',
                'external_id',
                'source_url',
                'search_keywords',
                'ingested_at',
                'content_hash',
            ]);
        });
    }
};
