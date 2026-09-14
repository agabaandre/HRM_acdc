<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('portal_ai_providers')) {
            Schema::create('portal_ai_providers', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('driver', 32);
                $table->string('api_endpoint', 512)->default('');
                $table->string('model', 191)->default('');
                $table->text('api_key')->nullable();
                $table->text('description')->nullable();
                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->index(['driver', 'is_active']);
            });
        }

        $exists = DB::table('portal_ai_providers')->where('slug', 'openai')->exists();
        if ($exists) {
            return;
        }

        $plainKey = trim((string) env('OPENAI_API_KEY', ''));
        $encrypted = $plainKey !== '' ? Crypt::encryptString($plainKey) : null;

        $now = now();
        DB::table('portal_ai_providers')->insert([
            'uuid' => (string) Str::uuid(),
            'name' => 'OpenAI',
            'slug' => 'openai',
            'driver' => 'openai',
            'api_endpoint' => 'https://api.openai.com/v1',
            'model' => 'gpt-4o-mini',
            'api_key' => $encrypted,
            'description' => 'Default OpenAI chat provider. Paste an API key to enable Fill with AI and connection tests.',
            'is_default' => true,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_ai_providers');
    }
};
