<?php

use App\Support\IdempotentSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        IdempotentSchema::create('oauth_clients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->nullableMorphs('owner');
            $table->string('name');
            $table->string('secret')->nullable();
            $table->string('provider')->nullable();
            $table->text('redirect_uris');
            $table->text('grant_types');
            $table->boolean('revoked');
            $table->timestamps();
        }, $this->getConnection());
    }

    public function down(): void
    {
        IdempotentSchema::dropIfExists('oauth_clients', $this->getConnection());
    }

    public function getConnection(): ?string
    {
        return $this->connection ?? config('passport.connection');
    }
};
