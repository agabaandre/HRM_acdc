<?php

namespace Database\Seeders;

use App\Models\MemoTypeDefinition;
use Illuminate\Database\Seeder;

class MemoTypeDefinitionsSeeder extends Seeder
{
    public function run(): void
    {
        $styles = array_keys(MemoTypeDefinition::SIGNATURE_STYLES);
        $defaultSchema = MemoTypeDefinition::defaultFieldsSchema();

        $rows = [
            ['slug' => 'approval-memo', 'name' => 'Approval Memo', 'ref_prefix' => 'AM-'],
            ['slug' => 'service-request-memo', 'name' => 'Service Request Memo', 'ref_prefix' => 'SRM-'],
            ['slug' => 'dsa-memo', 'name' => 'DSA Memo', 'ref_prefix' => 'DSA-'],
            ['slug' => 'note-verbale', 'name' => 'Note Verbale', 'ref_prefix' => 'NV-'],
            ['slug' => 'letters-mixed', 'name' => 'Letters (Confirmation of employment, Donation Award & Certificate, Invitations, Delegations)', 'ref_prefix' => 'LTR-'],
            ['slug' => 'purchase-order-approval', 'name' => 'Purchase Order Approval', 'ref_prefix' => 'POA-'],
            ['slug' => 'service-provider-contract-awards', 'name' => 'Service Provider Contract Awards', 'ref_prefix' => 'SPCA-'],
            ['slug' => 'request-for-payment', 'name' => 'Request for Payment', 'ref_prefix' => 'RFP-'],
            ['slug' => 'interview-score-sheet', 'name' => 'Interview Score Sheet', 'ref_prefix' => 'ISS-'],
            ['slug' => 'contracts-all-types', 'name' => 'Contracts (All types)', 'ref_prefix' => 'CNT-'],
            ['slug' => 'circular-memos', 'name' => 'Circular Memos', 'ref_prefix' => 'CIR-'],
            ['slug' => 'officer-in-charge-memo', 'name' => 'Officer in Charge Memo', 'ref_prefix' => 'OIC-'],
            ['slug' => 'sap-role-assignment-approval', 'name' => 'SAP Role Assignment Approval', 'ref_prefix' => 'SAPRA-'],
            ['slug' => 'confirmation-service-acceptance', 'name' => 'Confirmation of Service Acceptance', 'ref_prefix' => 'CSA-'],
            ['slug' => 'credit-notes', 'name' => 'Credit Notes', 'ref_prefix' => 'CN-'],
            ['slug' => 'leave-requests', 'name' => 'Leave Requests', 'ref_prefix' => 'LR-'],
            ['slug' => 'change-request-memo', 'name' => 'Change Request Memo', 'ref_prefix' => 'CRM-'],
            ['slug' => 'obituary', 'name' => 'Obituary', 'ref_prefix' => 'OB-'],
            ['slug' => 'office-supplies-voucher', 'name' => 'Office supplies request voucher', 'ref_prefix' => 'OSV-'],
            ['slug' => 'recruitment-request-forms', 'name' => 'Recruitment Request Forms (RRFs)', 'ref_prefix' => 'RRF-'],
            ['slug' => 'activity-request-forms', 'name' => 'Activity Request Forms (ARFs)', 'ref_prefix' => 'ARF-'],
            ['slug' => 'resignation-memo', 'name' => 'Resignation Memo', 'ref_prefix' => 'RES-'],
            ['slug' => 'service-provider-analysis', 'name' => 'Service Provider Analysis', 'ref_prefix' => 'SPA-'],
        ];

        foreach ($rows as $i => $row) {
            $slug = $row['slug'];
            $style = $styles[$i % count($styles)];

            $schema = $defaultSchema;
            if ($slug === 'activity-request-forms') {
                $schema = array_merge($defaultSchema, [
                    ['field' => 'justification', 'display' => 'Justification', 'field_type' => 'text_summernote', 'required' => false],
                    ['field' => 'amount', 'display' => 'Amount', 'field_type' => 'number', 'required' => false],
                ]);
            }
            if ($slug === 'interview-score-sheet') {
                $schema = [
                    ['field' => 'candidate_name', 'display' => 'Candidate', 'field_type' => 'text', 'required' => true],
                    ['field' => 'position', 'display' => 'Position', 'field_type' => 'text', 'required' => true],
                    ['field' => 'scores', 'display' => 'Scores / notes', 'field_type' => 'text_summernote', 'required' => true],
                ];
            }

            MemoTypeDefinition::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $row['name'],
                    'description' => null,
                    'ref_prefix' => $row['ref_prefix'],
                    'is_division_specific' => false,
                    'signature_style' => $style,
                    'fields_schema' => MemoTypeDefinition::normalizeFieldsSchemaRows($schema),
                    'is_system' => true,
                    'sort_order' => $i + 1,
                    'is_active' => true,
                ]
            );
        }
    }
}
