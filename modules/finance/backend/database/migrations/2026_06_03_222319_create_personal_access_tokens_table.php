<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Idempotent: table may already exist from Sanctum publish, staff-DB copy,
     * or a prior partial migrate.
     */
    public function up(): void
    {
        if ($this->personalAccessTokensExist()) {
            return;
        }

        try {
            Schema::create('personal_access_tokens', function (Blueprint $table) {
                $table->id();
                $table->morphs('tokenable');
                $table->text('name');
                $table->string('token', 64)->unique();
                $table->text('abilities')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable()->index();
                $table->timestamps();
            });
        } catch (QueryException $e) {
            if ($this->isTableAlreadyExists($e)) {
                return;
            }

            throw $e;
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
    }

    private function personalAccessTokensExist(): bool
    {
        try {
            if (Schema::hasTable('personal_access_tokens')) {
                return true;
            }
        } catch (\Throwable) {
            // Fall through to information_schema.
        }

        try {
            $database = DB::getDatabaseName();
            $row = DB::selectOne(
                'select 1 as ok from information_schema.tables
                 where table_schema = ? and table_name = ? limit 1',
                [$database, 'personal_access_tokens']
            );

            return $row !== null;
        } catch (\Throwable) {
            return false;
        }
    }

    private function isTableAlreadyExists(QueryException $e): bool
    {
        $sqlState = (string) ($e->errorInfo[0] ?? '');
        $driverCode = (int) ($e->errorInfo[1] ?? 0);
        $message = $e->getMessage();

        return $sqlState === '42S01'
            || $driverCode === 1050
            || str_contains($message, 'already exists');
    }
};
