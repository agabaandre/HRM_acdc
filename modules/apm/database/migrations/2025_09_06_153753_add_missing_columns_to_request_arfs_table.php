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
        Schema::table('request_arfs', function (Blueprint $table) {
            // Add missing columns if they don't exist
            if (!Schema::hasColumn('request_arfs', 'responsible_person_id')) {
                $table->integer('responsible_person_id')->nullable()->after('staff_id');
            }
            if (!Schema::hasColumn('request_arfs', 'total_amount')) {
                $table->decimal('total_amount', 15, 2)->nullable();
            }
        });

        // Update existing ARF records with missing data
        $this->updateExistingArfRecords();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('request_arfs', function (Blueprint $table) {
            $table->dropColumn(['responsible_person_id', 'total_amount']);
        });
    }

    /**
     * Update existing ARF records with missing data from source models
     */
    private function updateExistingArfRecords()
    {
        $arfs = \App\Models\RequestARF::whereNull('responsible_person_id')
            ->orWhereNull('total_amount')
            ->get();

        foreach ($arfs as $arf) {
            $sourceModel = $arf->getSourceModel();
            if (!$sourceModel) {
                continue;
            }

            $updates = [];

            // Update responsible_person_id from source
            if (!$arf->responsible_person_id && isset($sourceModel->responsible_person_id)) {
                $updates['responsible_person_id'] = $sourceModel->responsible_person_id;
            }

            // Update total_amount from source
            if (!$arf->total_amount) {
                if (isset($sourceModel->total_budget) && $sourceModel->total_budget) {
                    $updates['total_amount'] = $sourceModel->total_budget;
                } elseif (isset($sourceModel->requested_amount) && $sourceModel->requested_amount) {
                    $updates['total_amount'] = $sourceModel->requested_amount;
                } elseif ($arf->requested_amount) {
                    $updates['total_amount'] = $arf->requested_amount;
                }
            }

            // Update the ARF record if we have updates
            if (!empty($updates)) {
                $arf->update($updates);
            }
        }
    }
};