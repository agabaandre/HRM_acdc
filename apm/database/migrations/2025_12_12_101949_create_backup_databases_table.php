<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('backup_databases', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique()->comment('Database name');
            $table->string('display_name')->comment('Display name for the database');
            $table->string('host')->default('127.0.0.1')->comment('Database host');
            $table->integer('port')->default(3306)->comment('Database port');
            $table->string('username')->comment('Database username');
            $table->text('password')->comment('Encrypted database password');
            $table->boolean('is_active')->default(true)->comment('Whether this database is active for backups');
            $table->boolean('is_default')->default(false)->comment('Whether this is the default database');
            $table->integer('priority')->default(0)->comment('Backup priority (higher = backup first)');
            $table->text('description')->nullable()->comment('Description of the database');
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('is_active');
            $table->index('is_default');
            $table->index('priority');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('backup_databases');
    }
};
