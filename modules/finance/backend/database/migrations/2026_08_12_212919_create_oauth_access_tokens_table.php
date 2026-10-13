<?php

use App\Support\IdempotentSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        IdempotentSchema::create('oauth_access_tokens', function (Blueprint $table) {
            $table->char('id', 80)->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->foreignUuid('client_id');
            $table->string('name')->nullable();
            $table->text('scopes')->nullable();
            $table->boolean('revoked');
            $table->timestamps();
            $table->dateTime('expires_at')->nullable();
        }, $this->getConnection());
    }

    public function down(): void
    {
        IdempotentSchema::dropIfExists('oauth_access_tokens', $this->getConnection());
    }

    public function getConnection(): ?string
    {
        return $this->connection ?? config('passport.connection');
    }
};
