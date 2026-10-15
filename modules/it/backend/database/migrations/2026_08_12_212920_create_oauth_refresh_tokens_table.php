<?php

use App\Support\IdempotentSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        IdempotentSchema::create('oauth_refresh_tokens', function (Blueprint $table) {
            $table->char('id', 80)->primary();
            $table->char('access_token_id', 80)->index();
            $table->boolean('revoked');
            $table->dateTime('expires_at')->nullable();
        }, $this->getConnection());
    }

    public function down(): void
    {
        IdempotentSchema::dropIfExists('oauth_refresh_tokens', $this->getConnection());
    }

    public function getConnection(): ?string
    {
        return $this->connection ?? config('passport.connection');
    }
};
