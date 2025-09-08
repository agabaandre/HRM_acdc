<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Define all indexes to be created
        $indexes = [
            // Staff table indexes
            'staff' => [
                'staff_division_id_index' => 'division_id',
                'staff_duty_station_id_index' => 'duty_station_id',
                'staff_supervisor_id_index' => 'supervisor_id',
                'staff_status_index' => 'status',
                'staff_work_email_index' => 'work_email',
                'staff_private_email_index' => 'private_email',
                'staff_contract_type_index' => 'contract_type',
                'staff_nationality_index' => 'nationality',
                'staff_grade_index' => 'grade',
                'staff_job_name_index' => 'job_name',
                'staff_division_id_status_index' => 'division_id,status',
                'staff_status_contract_type_index' => 'status,contract_type',
                'staff_division_id_contract_type_index' => 'division_id,contract_type',
            ],
            
            // Divisions table indexes
            'divisions' => [
                'divisions_directorate_id_index' => 'directorate_id',
                'divisions_division_head_index' => 'division_head',
                'divisions_focal_person_index' => 'focal_person',
                'divisions_admin_assistant_index' => 'admin_assistant',
                'divisions_finance_officer_index' => 'finance_officer',
                'divisions_head_oic_id_index' => 'head_oic_id',
                'divisions_director_id_index' => 'director_id',
                'divisions_director_oic_id_index' => 'director_oic_id',
                'divisions_division_name_index' => 'division_name',
                'divisions_division_short_name_index' => 'division_short_name',
                'divisions_category_index' => 'category',
            ],
            
            // Matrices table indexes
            'matrices' => [
                'matrices_focal_person_id_index' => 'focal_person_id',
                'matrices_staff_id_index' => 'staff_id',
                'matrices_forward_workflow_id_index' => 'forward_workflow_id',
                'matrices_reverse_workflow_id_index' => 'reverse_workflow_id',
                'matrices_year_index' => 'year',
                'matrices_quarter_index' => 'quarter',
                'matrices_approval_level_index' => 'approval_level',
                'matrices_next_approval_level_index' => 'next_approval_level',
                'matrices_overall_status_index' => 'overall_status',
                'matrices_division_id_year_quarter_index' => 'division_id,year,quarter',
                'matrices_division_id_overall_status_index' => 'division_id,overall_status',
                'matrices_staff_id_overall_status_index' => 'staff_id,overall_status',
                'matrices_approval_level_overall_status_index' => 'approval_level,overall_status',
                'matrices_year_quarter_overall_status_index' => 'year,quarter,overall_status',
            ],
            
            // Activities table indexes
            'activities' => [
                'activities_matrix_id_index' => 'matrix_id',
                'activities_staff_id_index' => 'staff_id',
                'activities_responsible_person_id_index' => 'responsible_person_id',
                'activities_request_type_id_index' => 'request_type_id',
                'activities_fund_type_id_index' => 'fund_type_id',
                'activities_forward_workflow_id_index' => 'forward_workflow_id',
                'activities_reverse_workflow_id_index' => 'reverse_workflow_id',
                'activities_division_id_index' => 'division_id',
                'activities_date_from_index' => 'date_from',
                'activities_date_to_index' => 'date_to',
                'activities_total_participants_index' => 'total_participants',
                'activities_approval_level_index' => 'approval_level',
                'activities_overall_status_index' => 'overall_status',
                'activities_is_single_memo_index' => 'is_single_memo',
                'activities_matrix_id_overall_status_index' => 'matrix_id,overall_status',
                'activities_staff_id_overall_status_index' => 'staff_id,overall_status',
                'activities_division_id_overall_status_index' => 'division_id,overall_status',
                'activities_date_from_date_to_index' => 'date_from,date_to',
                'activities_approval_level_overall_status_index' => 'approval_level,overall_status',
                'activities_fund_type_id_overall_status_index' => 'fund_type_id,overall_status',
            ],
            
            // Service Requests table indexes
            'service_requests' => [
                'service_requests_staff_id_index' => 'staff_id',
                'service_requests_activity_id_index' => 'activity_id',
                'service_requests_division_id_index' => 'division_id',
                'service_requests_forward_workflow_id_index' => 'forward_workflow_id',
                'service_requests_reverse_workflow_id_index' => 'reverse_workflow_id',
                'service_requests_fund_type_id_index' => 'fund_type_id',
                'service_requests_responsible_person_id_index' => 'responsible_person_id',
                'service_requests_request_number_index' => 'request_number',
                'service_requests_request_date_index' => 'request_date',
                'service_requests_service_title_index' => 'service_title',
                'service_requests_status_index' => 'status',
                'service_requests_priority_index' => 'priority',
                'service_requests_service_type_index' => 'service_type',
                'service_requests_approval_level_index' => 'approval_level',
                'service_requests_next_approval_level_index' => 'next_approval_level',
                'service_requests_model_type_index' => 'model_type',
                'service_requests_source_id_index' => 'source_id',
                'service_requests_source_type_index' => 'source_type',
                'service_requests_staff_id_status_index' => 'staff_id,status',
                'service_requests_division_id_status_index' => 'division_id,status',
                'service_requests_approval_level_status_index' => 'approval_level,status',
                'service_requests_model_type_source_type_index' => 'model_type,source_type',
            ],
            
            // Request ARFs table indexes
            'request_arfs' => [
                'request_arfs_staff_id_index' => 'staff_id',
                'request_arfs_responsible_person_id_index' => 'responsible_person_id',
                'request_arfs_division_id_index' => 'division_id',
                'request_arfs_forward_workflow_id_index' => 'forward_workflow_id',
                'request_arfs_reverse_workflow_id_index' => 'reverse_workflow_id',
                'request_arfs_fund_type_id_index' => 'fund_type_id',
                'request_arfs_funder_id_index' => 'funder_id',
                'request_arfs_arf_number_index' => 'arf_number',
                'request_arfs_request_date_index' => 'request_date',
                'request_arfs_activity_title_index' => 'activity_title',
                'request_arfs_overall_status_index' => 'overall_status',
                'request_arfs_approval_level_index' => 'approval_level',
                'request_arfs_next_approval_level_index' => 'next_approval_level',
                'request_arfs_model_type_index' => 'model_type',
                'request_arfs_source_id_index' => 'source_id',
                'request_arfs_source_type_index' => 'source_type',
                'request_arfs_staff_id_overall_status_index' => 'staff_id,overall_status',
                'request_arfs_division_id_overall_status_index' => 'division_id,overall_status',
                'request_arfs_approval_level_overall_status_index' => 'approval_level,overall_status',
                'request_arfs_model_type_source_type_index' => 'model_type,source_type',
            ],
            
            // Non Travel Memos table indexes
            'non_travel_memos' => [
                'non_travel_memos_staff_id_index' => 'staff_id',
                'non_travel_memos_division_id_index' => 'division_id',
                'non_travel_memos_non_travel_memo_category_id_index' => 'non_travel_memo_category_id',
                'non_travel_memos_forward_workflow_id_index' => 'forward_workflow_id',
                'non_travel_memos_reverse_workflow_id_index' => 'reverse_workflow_id',
                'non_travel_memos_fund_type_id_index' => 'fund_type_id',
                'non_travel_memos_memo_date_index' => 'memo_date',
                'non_travel_memos_activity_title_index' => 'activity_title',
                'non_travel_memos_overall_status_index' => 'overall_status',
                'non_travel_memos_approval_level_index' => 'approval_level',
                'non_travel_memos_next_approval_level_index' => 'next_approval_level',
                'non_travel_memos_is_draft_index' => 'is_draft',
                'non_travel_memos_staff_id_overall_status_index' => 'staff_id,overall_status',
                'non_travel_memos_division_id_overall_status_index' => 'division_id,overall_status',
                'non_travel_memos_approval_level_overall_status_index' => 'approval_level,overall_status',
                'non_travel_memos_is_draft_overall_status_index' => 'is_draft,overall_status',
            ],
            
            // Special Memos table indexes
            'special_memos' => [
                'special_memos_staff_id_index' => 'staff_id',
                'special_memos_division_id_index' => 'division_id',
                'special_memos_request_type_id_index' => 'request_type_id',
                'special_memos_forward_workflow_id_index' => 'forward_workflow_id',
                'special_memos_fund_type_id_index' => 'fund_type_id',
                'special_memos_responsible_person_id_index' => 'responsible_person_id',
                'special_memos_activity_title_index' => 'activity_title',
                'special_memos_overall_status_index' => 'overall_status',
                'special_memos_approval_level_index' => 'approval_level',
                'special_memos_next_approval_level_index' => 'next_approval_level',
                'special_memos_is_draft_index' => 'is_draft',
                'special_memos_staff_id_overall_status_index' => 'staff_id,overall_status',
                'special_memos_division_id_overall_status_index' => 'division_id,overall_status',
                'special_memos_approval_level_overall_status_index' => 'approval_level,overall_status',
                'special_memos_is_draft_overall_status_index' => 'is_draft,overall_status',
            ],
            
            // Approval Trails table indexes
            'approval_trails' => [
                'approval_trails_model_id_index' => 'model_id',
                'approval_trails_matrix_id_index' => 'matrix_id',
                'approval_trails_staff_id_index' => 'staff_id',
                'approval_trails_oic_staff_id_index' => 'oic_staff_id',
                'approval_trails_forward_workflow_id_index' => 'forward_workflow_id',
                'approval_trails_model_type_index' => 'model_type',
                'approval_trails_action_index' => 'action',
                'approval_trails_approval_order_index' => 'approval_order',
                'approval_trails_created_at_index' => 'created_at',
                'approval_trails_model_id_model_type_index' => 'model_id,model_type',
                'approval_trails_model_id_model_type_action_index' => 'model_id,model_type,action',
                'approval_trails_staff_id_action_index' => 'staff_id,action',
                'approval_trails_approval_order_action_index' => 'approval_order,action',
                'approval_trails_matrix_id_approval_order_index' => 'matrix_id,approval_order',
            ],
            
            // Activity Approval Trails table indexes
            'activity_approval_trails' => [
                'activity_approval_trails_activity_id_index' => 'activity_id',
                'activity_approval_trails_matrix_id_index' => 'matrix_id',
                'activity_approval_trails_staff_id_index' => 'staff_id',
                'activity_approval_trails_oic_staff_id_index' => 'oic_staff_id',
                'activity_approval_trails_forward_workflow_id_index' => 'forward_workflow_id',
                'activity_approval_trails_action_index' => 'action',
                'activity_approval_trails_approval_order_index' => 'approval_order',
                'activity_approval_trails_created_at_index' => 'created_at',
                'activity_approval_trails_activity_id_action_index' => 'activity_id,action',
                'activity_approval_trails_matrix_id_approval_order_index' => 'matrix_id,approval_order',
                'activity_approval_trails_staff_id_action_index' => 'staff_id,action',
                'activity_approval_trails_approval_order_action_index' => 'approval_order,action',
            ],
            
            // Service Request Approval Trails table indexes
            'service_request_approval_trails' => [
                'service_request_approval_trails_service_request_id_index' => 'service_request_id',
                'service_request_approval_trails_staff_id_index' => 'staff_id',
                'service_request_approval_trails_action_index' => 'action',
                'service_request_approval_trails_created_at_index' => 'created_at',
                'service_request_approval_trails_service_request_id_action_index' => 'service_request_id,action',
                'service_request_approval_trails_staff_id_action_index' => 'staff_id,action',
            ],
            
            // Non Travel Memo Approval Trails table indexes
            'non_travel_memo_approval_trails' => [
                'non_travel_memo_approval_trails_non_travel_memo_id_index' => 'non_travel_memo_id',
                'non_travel_memo_approval_trails_staff_id_index' => 'staff_id',
                'non_travel_memo_approval_trails_action_index' => 'action',
                'non_travel_memo_approval_trails_created_at_index' => 'created_at',
                'non_travel_memo_approval_trails_non_travel_memo_id_action_index' => 'non_travel_memo_id,action',
                'non_travel_memo_approval_trails_staff_id_action_index' => 'staff_id,action',
            ],
            
            // Fund Codes table indexes
            'fund_codes' => [
                'fund_codes_division_id_index' => 'division_id',
                'fund_codes_fund_type_id_index' => 'fund_type_id',
                'fund_codes_funder_id_index' => 'funder_id',
                'fund_codes_code_index' => 'code',
                'fund_codes_year_index' => 'year',
                'fund_codes_is_active_index' => 'is_active',
                'fund_codes_division_id_year_index' => 'division_id,year',
                'fund_codes_fund_type_id_year_index' => 'fund_type_id,year',
                'fund_codes_division_id_is_active_index' => 'division_id,is_active',
                'fund_codes_year_is_active_index' => 'year,is_active',
            ],
            
            // Fund Code Transactions table indexes
            'fund_code_transactions' => [
                'fund_code_transactions_fund_code_id_index' => 'fund_code_id',
                'fund_code_transactions_activity_id_index' => 'activity_id',
                'fund_code_transactions_matrix_id_index' => 'matrix_id',
                'fund_code_transactions_activity_budget_id_index' => 'activity_budget_id',
                'fund_code_transactions_created_by_index' => 'created_by',
                'fund_code_transactions_channel_index' => 'channel',
                'fund_code_transactions_is_reversal_index' => 'is_reversal',
                'fund_code_transactions_created_at_index' => 'created_at',
                'fund_code_transactions_fund_code_id_created_at_index' => 'fund_code_id,created_at',
                'fund_code_transactions_activity_id_channel_index' => 'activity_id,channel',
                'fund_code_transactions_is_reversal_created_at_index' => 'is_reversal,created_at',
            ],
            
            // Workflow Definitions table indexes
            'workflow_definitions' => [
                'workflow_definitions_workflow_id_index' => 'workflow_id',
                'workflow_definitions_approval_order_index' => 'approval_order',
                'workflow_definitions_is_enabled_index' => 'is_enabled',
                'workflow_definitions_is_division_specific_index' => 'is_division_specific',
                'workflow_definitions_category_index' => 'category',
                'workflow_definitions_fund_type_index' => 'fund_type',
                'workflow_definitions_workflow_id_approval_order_index' => 'workflow_id,approval_order',
                'workflow_definitions_workflow_id_is_enabled_index' => 'workflow_id,is_enabled',
                'workflow_definitions_approval_order_is_enabled_index' => 'approval_order,is_enabled',
            ],
            
            // Approvers table indexes
            'approvers' => [
                'approvers_workflow_dfn_id_index' => 'workflow_dfn_id',
                'approvers_staff_id_index' => 'staff_id',
                'approvers_oic_staff_id_index' => 'oic_staff_id',
                'approvers_start_date_index' => 'start_date',
                'approvers_end_date_index' => 'end_date',
                'approvers_workflow_dfn_id_staff_id_index' => 'workflow_dfn_id,staff_id',
                'approvers_workflow_dfn_id_oic_staff_id_index' => 'workflow_dfn_id,oic_staff_id',
                'approvers_staff_id_start_date_end_date_index' => 'staff_id,start_date,end_date',
            ],
            
            // Notifications table indexes
            'notifications' => [
                'notifications_staff_id_index' => 'staff_id',
                'notifications_matrix_id_index' => 'matrix_id',
                'notifications_type_index' => 'type',
                'notifications_is_read_index' => 'is_read',
                'notifications_created_at_index' => 'created_at',
                'notifications_staff_id_is_read_index' => 'staff_id,is_read',
                'notifications_staff_id_type_index' => 'staff_id,type',
                'notifications_is_read_created_at_index' => 'is_read,created_at',
            ],
            
            // Participant Schedules table indexes
            'participant_schedules' => [
                'participant_schedules_participant_id_index' => 'participant_id',
                'participant_schedules_activity_id_index' => 'activity_id',
                'participant_schedules_matrix_id_index' => 'matrix_id',
                'participant_schedules_division_id_index' => 'division_id',
                'participant_schedules_quarter_index' => 'quarter',
                'participant_schedules_year_index' => 'year',
                'participant_schedules_is_home_division_index' => 'is_home_division',
                'participant_schedules_international_travel_index' => 'international_travel',
                'participant_schedules_participant_id_quarter_year_index' => 'participant_id,quarter,year',
                'participant_schedules_activity_id_is_home_division_index' => 'activity_id,is_home_division',
                'participant_schedules_matrix_id_quarter_year_index' => 'matrix_id,quarter,year',
            ],
            
            // Activity Budgets table indexes
            'activity_budgets' => [
                'activity_budgets_activity_id_index' => 'activity_id',
                'activity_budgets_fund_code_index' => 'fund_code',
                'activity_budgets_fund_type_id_index' => 'fund_type_id',
                'activity_budgets_amount_index' => 'amount',
                'activity_budgets_activity_id_fund_type_id_index' => 'activity_id,fund_type_id',
                'activity_budgets_fund_code_fund_type_id_index' => 'fund_code,fund_type_id',
            ],
            
            // Request Types table indexes
            'request_types' => [
                'request_types_name_index' => 'name',
            ],
            
            // Fund Types table indexes
            'fund_types' => [
                'fund_types_name_index' => 'name',
            ],
            
            // Funders table indexes
            'funders' => [
                'funders_name_index' => 'name',
                'funders_is_active_index' => 'is_active',
            ],
            
            // Locations table indexes
            'locations' => [
                'locations_name_index' => 'name',
            ],
            
            // Directorates table indexes
            'directorates' => [
                'directorates_name_index' => 'name',
                'directorates_is_active_index' => 'is_active',
            ],
            
            // Duty Stations table indexes
            'duty_stations' => [
                'duty_stations_name_index' => 'name',
                'duty_stations_is_active_index' => 'is_active',
            ],
            
            // Non Travel Memo Categories table indexes
            'non_travel_memo_categories' => [
                'non_travel_memo_categories_name_index' => 'name',
            ],
            
            // Cost Items table indexes
            'cost_items' => [
                'cost_items_name_index' => 'name',
            ],
        ];

        // Create indexes using Laravel Schema with existence checks
        foreach ($indexes as $tableName => $tableIndexes) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) use ($tableIndexes, $tableName) {
                    foreach ($tableIndexes as $indexName => $columns) {
                        // Check if index already exists
                        if (!$this->indexExists($tableName, $indexName)) {
                            $columnArray = explode(',', $columns);
                            if (count($columnArray) > 1) {
                                $table->index($columnArray, $indexName);
                            } else {
                                $table->index($columns, $indexName);
                            }
                            echo "Created index: {$indexName} on {$tableName}\n";
                        } else {
                            echo "Skipped index: {$indexName} on {$tableName} (already exists)\n";
                        }
                    }
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop all indexes in reverse order
        $tables = [
            'staff', 'divisions', 'matrices', 'activities', 'service_requests',
            'request_arfs', 'non_travel_memos', 'special_memos', 'approval_trails',
            'activity_approval_trails', 'service_request_approval_trails',
            'non_travel_memo_approval_trails', 'fund_codes', 'fund_code_transactions',
            'workflow_definitions', 'approvers', 'notifications', 'participant_schedules',
            'activity_budgets', 'request_types', 'fund_types', 'funders',
            'locations', 'directorates', 'duty_stations', 'non_travel_memo_categories',
            'cost_items'
        ];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    // Get all indexes for the table and drop them
                    $indexes = $this->getTableIndexes($tableName);
                    foreach ($indexes as $index) {
                        if ($index !== 'PRIMARY') {
                            $table->dropIndex($index);
                        }
                    }
                });
            }
        }
    }

    /**
     * Check if an index exists on a table
     */
    private function indexExists($tableName, $indexName)
    {
        try {
            $result = DB::select("SHOW INDEX FROM `{$tableName}` WHERE Key_name = ?", [$indexName]);
            return count($result) > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get all indexes for a table
     */
    private function getTableIndexes($tableName)
    {
        $indexes = [];
        try {
            $result = DB::select("SHOW INDEX FROM `{$tableName}`");
            foreach ($result as $row) {
                $indexes[] = $row->Key_name;
            }
        } catch (\Exception $e) {
            // Table might not exist
        }
        
        return array_unique($indexes);
    }
};