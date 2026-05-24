<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use App\Traits\HasApprovalWorkflow;
use App\Traits\HasDocumentNumber;
use App\Traits\ProvidesMemoIndexStatusMeta;
use App\Traits\TrimsSummernoteHtmlFields;
use iamfarhad\LaravelAuditLog\Traits\Auditable;

class ChangeRequest extends Model
{
    use HasFactory, HasApprovalWorkflow, HasDocumentNumber, Auditable, ProvidesMemoIndexStatusMeta, TrimsSummernoteHtmlFields;

    const STATUS_DRAFT = 'draft';
    const STATUS_SUBMITTED = 'submitted';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    protected $table = 'change_request';

    protected $fillable = [
        'parent_memo_id',
        'parent_memo_model',
        'activity_id',
        'special_memo_id',
        'non_travel_memo_id',
        'request_arf_id',
        'service_request_id',
        'has_budget_id_changed',
        'has_internal_participants_changed',
        'has_number_of_participants_changed',
        'has_participant_days_changed',
        'has_request_type_id_changed',
        'request_type_id',
        'has_total_external_participants_changed',
        'has_location_changed',
        'has_memo_date_changed',
        'has_date_stayed_quarter',
        'has_activity_title_changed',
        'has_activity_request_remarks_changed',
        'has_is_single_memo_changed',
        'has_budget_breakdown_changed',
        'has_status_changed',
        'has_fund_type_id_changed',
        'document_number',
        'forward_workflow_id',
        'workplan_activity_code',
        'matrix_id',
        'division_id',
        'staff_id',
        'responsible_person_id',
        'supporting_reasons',
        'date_from',
        'date_to',
        'memo_date',
        'location_id',
        'total_participants',
        'internal_participants',
        'total_external_participants',
        'division_staff_request',
        'budget_id',
        'key_result_area',
        'justification',
        'non_travel_memo_category_id',
        'activity_title',
        'background',
        'activity_request_remarks',
        'is_single_memo',
        'budget_breakdown',
        'available_budget',
        'attachment',
        'status',
        'fund_type_id',
        'activity_ref',
        'approval_level',
        'next_approval_level',
        'overall_status',
        'previous_overall_status',
        'approval_order_map',
    ];

    protected $hidden = ['created_at', 'updated_at'];

    protected $appends = ['formatted_dates', 'status', 'my_last_action', 'has_passed_at_current_level', 'my_current_level_action'];

    protected $casts = [
        'id' => 'integer',
        'parent_memo_id' => 'integer',
        'activity_id' => 'integer',
        'special_memo_id' => 'integer',
        'non_travel_memo_id' => 'integer',
        'request_arf_id' => 'integer',
        'service_request_id' => 'integer',
        'request_type_id' => 'integer',
        'fund_type_id' => 'integer',
        'matrix_id' => 'integer',
        'division_id' => 'integer',
        'staff_id' => 'integer',
        'responsible_person_id' => 'integer',
        'non_travel_memo_category_id' => 'integer',
        'forward_workflow_id' => 'integer',
        'approval_level' => 'integer',
        'next_approval_level' => 'integer',
        'total_participants' => 'integer',
        'total_external_participants' => 'integer',
        'available_budget' => 'decimal:2',
        'date_from' => 'date',
        'date_to' => 'date',
        'memo_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        
        // JSON fields
        'location_id' => 'array',
        'internal_participants' => 'array',
        'division_staff_request' => 'array',
        'budget_id' => 'array',
        'budget_breakdown' => 'array',
        'attachment' => 'array',
        
        // Boolean fields
        'has_budget_id_changed' => 'boolean',
        'has_internal_participants_changed' => 'boolean',
        'has_number_of_participants_changed' => 'boolean',
        'has_participant_days_changed' => 'boolean',
        'has_request_type_id_changed' => 'boolean',
        'has_total_external_participants_changed' => 'boolean',
        'has_location_changed' => 'boolean',
        'has_memo_date_changed' => 'boolean',
        'has_date_stayed_quarter' => 'boolean',
        'has_activity_title_changed' => 'boolean',
        'has_activity_request_remarks_changed' => 'boolean',
        'has_is_single_memo_changed' => 'boolean',
        'has_budget_breakdown_changed' => 'boolean',
        'has_status_changed' => 'boolean',
        'has_fund_type_id_changed' => 'boolean',
        'is_single_memo' => 'boolean',
    ];

    /**
     * Get the parent memo (polymorphic relationship)
     */
    public function parentMemo(): MorphTo
    {
        return $this->morphTo('parent_memo', 'parent_memo_model', 'parent_memo_id');
    }

    /**
     * Get the activity that this change request is for
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    /**
     * Get the special memo that this change request is for
     */
    public function specialMemo(): BelongsTo
    {
        return $this->belongsTo(SpecialMemo::class);
    }

    /**
     * Get the non-travel memo that this change request is for
     */
    public function nonTravelMemo(): BelongsTo
    {
        return $this->belongsTo(NonTravelMemo::class);
    }

    /**
     * Get the request ARF that this change request is for
     */
    public function requestArf(): BelongsTo
    {
        return $this->belongsTo(RequestArf::class);
    }

    /**
     * Get the service request that this change request is for
     */
    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    /**
     * Get the matrix that this change request belongs to
     */
    public function matrix(): BelongsTo
    {
        return $this->belongsTo(Matrix::class);
    }

    /**
     * Get the division that this change request belongs to
     */
    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    /**
     * Workflow id when forward_workflow_id is missing (aligned with ChangeRequestController::determineWorkflowId).
     */
    public function inferWorkflowIdFromChangeFlags(): int
    {
        $hasBudgetChanges = $this->has_budget_id_changed || $this->has_budget_breakdown_changed;
        if ($hasBudgetChanges) {
            return 1;
        }

        $hasParticipantChanges = $this->has_internal_participants_changed
            || $this->has_number_of_participants_changed
            || $this->has_participant_days_changed
            || $this->has_total_external_participants_changed;

        $hasDateChanges = $this->has_memo_date_changed;
        $dateStayedInQuarter = $this->has_date_stayed_quarter;

        if ($hasDateChanges && $dateStayedInQuarter && !$hasParticipantChanges) {
            return 6;
        }

        if (($hasDateChanges && !$dateStayedInQuarter) || $hasParticipantChanges) {
            return 7;
        }

        return 6;
    }

    protected function memoIndexResolvedWorkflowId(): ?int
    {
        if ($this->forward_workflow_id) {
            return (int) $this->forward_workflow_id;
        }
        if (in_array($this->overall_status, ['returned', 'draft', 'submitted', 'pending'], true)) {
            return $this->inferWorkflowIdFromChangeFlags();
        }

        return null;
    }

    /**
     * Get the staff member who created this change request
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_id', 'staff_id');
    }

    /**
     * Get the responsible person for this change request
     */
    public function responsiblePerson(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'responsible_person_id', 'staff_id');
    }

    /**
     * Get the request type for this change request
     */
    public function requestType(): BelongsTo
    {
        return $this->belongsTo(RequestType::class);
    }

    /**
     * Get the fund type for this change request
     */
    public function fundType(): BelongsTo
    {
        return $this->belongsTo(FundType::class);
    }

    /**
     * Get the non-travel memo category for this change request
     */
    public function nonTravelMemoCategory(): BelongsTo
    {
        return $this->belongsTo(NonTravelMemoCategory::class);
    }

    /**
     * Get the forward workflow for this change request
     */

    /**
     * Get the approval trails for this change request
     */
    // public function approvalTrails(): HasMany
    // {
    //     return $this->hasMany(ApprovalTrail::class, 'model_id')
    //         ->where('model_type', 'change_request');
    // }

    /**
     * Get the budget items for this change request
     * Note: These models don't exist yet, so commenting out for now
     */
    // public function budgetItems(): HasMany
    // {
    //     return $this->hasMany(ChangeRequestBudget::class);
    // }

    /**
     * Get the participants for this change request
     * Note: These models don't exist yet, so commenting out for now
     */
    // public function participants(): HasMany
    // {
    //     return $this->hasMany(ChangeRequestParticipant::class);
    // }

    /**
     * Scope to get change requests by status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('overall_status', $status);
    }

    /**
     * Scope to get change requests by approval level
     */
    public function scopeByApprovalLevel($query, $level)
    {
        return $query->where('approval_level', $level);
    }

    /**
     * Scope to get change requests for a specific staff member
     */
    public function scopeForStaff($query, $staffId)
    {
        return $query->where('staff_id', $staffId);
    }

    /**
     * Scope to get change requests for a specific division
     */
    public function scopeForDivision($query, $divisionId)
    {
        return $query->where('division_id', $divisionId);
    }

    /**
     * Get the current approver role for this change request
     */
    public function getCurrentApproverRoleAttribute()
    {
        // This would be implemented based on your workflow logic
        return 'Approver';
    }

    /**
     * Check if this change request has any changes
     */
    public function hasAnyChanges(): bool
    {
        return $this->has_budget_id_changed ||
               $this->has_internal_participants_changed ||
               $this->has_number_of_participants_changed ||
               $this->has_participant_days_changed ||
               $this->has_request_type_id_changed ||
               $this->has_total_external_participants_changed ||
               $this->has_location_changed ||
               $this->has_memo_date_changed ||
               $this->has_date_stayed_quarter ||
               $this->has_activity_title_changed ||
               $this->has_activity_request_remarks_changed ||
               $this->has_is_single_memo_changed ||
               $this->has_budget_breakdown_changed ||
               $this->has_status_changed ||
               $this->has_fund_type_id_changed;
    }

    /**
     * Get the summary of changes made
     */
    public function getChangesSummaryAttribute(): array
    {
        $changes = [];
        
        if ($this->has_budget_id_changed) $changes[] = 'Budget Code';
        if ($this->has_internal_participants_changed) $changes[] = 'Internal Participants';
        if ($this->has_number_of_participants_changed) $changes[] = 'Number of Participants';
        if ($this->has_participant_days_changed) $changes[] = 'Participant Days';
        if ($this->has_request_type_id_changed) $changes[] = 'Request Type';
        if ($this->has_total_external_participants_changed) $changes[] = 'External Participants';
        if ($this->has_location_changed) $changes[] = 'Location';
        if ($this->has_memo_date_changed) $changes[] = 'Memo Date';
        if ($this->has_date_stayed_quarter) $changes[] = 'Date Stayed Quarter';
        if ($this->has_activity_title_changed) $changes[] = 'Activity Title';
        if ($this->has_activity_request_remarks_changed) $changes[] = 'Request for Approval';
        if ($this->has_budget_breakdown_changed) $changes[] = 'Budget Breakdown';
        if ($this->has_status_changed) $changes[] = 'Status';
        if ($this->has_fund_type_id_changed) $changes[] = 'Fund Type';
        
        return $changes;
    }

    public function forwardWorkflow(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Workflow::class, 'forward_workflow_id');
    }

    /**
     * Reverse workflow relationship.
     */

    // --- Accessors & Utility ---

    public function getFormattedDatesAttribute(): string
    {
        if ($this->date_from && $this->date_to) {
            return \Carbon\Carbon::parse($this->date_from)->format('M j, Y') . ' - ' . \Carbon\Carbon::parse($this->date_to)->format('M j, Y');
        }
        return '';
    }

    /**
     * Get the parent memo show URL
     */
    public function getParentMemoUrlAttribute(): ?string
    {
        if (!$this->parent_memo_model || !$this->parent_memo_id) {
            return null;
        }

        $modelName = class_basename($this->parent_memo_model);
        
        return match($modelName) {
            'Activity' => route('activities.single-memos.show', $this->parent_memo_id),
            'SpecialMemo' => route('special-memo.show', $this->parent_memo_id),
            'NonTravelMemo' => route('non-travel.show', $this->parent_memo_id),
            'RequestArf' => route('request-arf.show', $this->parent_memo_id),
            'ServiceRequest' => route('service-requests.show', $this->parent_memo_id),
            default => null,
        };
    }

    /**
     * Get the parent memo document number
     */
    public function getParentMemoDocumentNumberAttribute(): ?string
    {
        if (!$this->parent_memo_model || !$this->parent_memo_id) {
            return null;
        }

        $parentMemo = $this->parentMemo;
        if (!$parentMemo) {
            // Try to load it manually
            $modelClass = $this->parent_memo_model;
            $parentMemo = $modelClass::find($this->parent_memo_id);
        }

        return $parentMemo?->document_number ?? null;
    }

    /**
     * Whether status and approval level allow the focal/responsible user to open the parent memo editor
     * (route change-requests.edit). Draft: only at approval level 0. Returned: levels 0 or 1.
     * Rejected and pending: unchanged (any level where that status applies).
     */
    public function workflowAllowsSubmitterParentMemoEdit(): bool
    {
        $st = strtolower(trim((string) ($this->overall_status ?? '')));
        $level = (int) ($this->approval_level ?? 0);

        if (in_array($st, [self::STATUS_REJECTED, 'pending'], true)) {
            return true;
        }
        if ($st === self::STATUS_DRAFT) {
            return $level === 0;
        }
        if ($st === 'returned') {
            return in_array($level, [0, 1], true);
        }

        return false;
    }

    public function isOwnedOrResponsibleByStaffId(?int $staffId): bool
    {
        if ($staffId === null) {
            return false;
        }

        return (int) $this->staff_id === $staffId
            || ($this->responsible_person_id !== null && (int) $this->responsible_person_id === $staffId);
    }

    /**
     * Creator, responsible person, or effective Head of Division (division_head or active head OIC).
     */
    public function isOwnedResponsibleOrEffectiveDivisionHeadByStaffId(?int $staffId): bool
    {
        if ($this->isOwnedOrResponsibleByStaffId($staffId)) {
            return true;
        }
        if ($staffId === null) {
            return false;
        }

        $division = $this->relationLoaded('division') ? $this->division : null;
        if (! $division && $this->division_id) {
            $division = Division::query()->find((int) $this->division_id);
        }
        if (! $division) {
            return false;
        }

        if (function_exists('effective_division_head_staff_id')) {
            $headId = effective_division_head_staff_id($division);

            return $headId !== null && (int) $headId === (int) $staffId;
        }

        return (int) ($division->division_head ?? 0) === (int) $staffId;
    }

    /**
     * When editing a parent memo with ?change_request=1&change_request_id=…, verify the CR targets this memo
     * and the viewer may edit (creator, responsible, or effective division head).
     */
    public static function viewerMayEditParentMemoRoute(?int $changeRequestId, object $parentMemo, int $viewerStaffId): bool
    {
        if (! $changeRequestId || $viewerStaffId <= 0) {
            return false;
        }

        $cr = self::query()->with('division')->find($changeRequestId);
        if (! $cr) {
            return false;
        }
        if ((int) $cr->parent_memo_id !== (int) $parentMemo->id) {
            return false;
        }
        $expectedModel = get_class($parentMemo);
        if ((string) $cr->parent_memo_model !== $expectedModel) {
            return false;
        }

        return $cr->isOwnedResponsibleOrEffectiveDivisionHeadByStaffId($viewerStaffId)
            && $cr->workflowAllowsSubmitterParentMemoEdit();
    }

    public function getRecipientsAttribute($value)
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }

    /**
     * Decode attachment JSON from DB (handles legacy double-encoded non-travel values).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function decodeAttachmentPayload(mixed $value): array
    {
        $data = $value;
        for ($i = 0; $i < 4; $i++) {
            if (is_array($data)) {
                return array_values(array_filter($data, 'is_array'));
            }
            if (! is_string($data)) {
                break;
            }
            $trimmed = trim($data);
            if ($trimmed === '' || $trimmed === 'null') {
                return [];
            }
            $decoded = json_decode($data, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                break;
            }
            $data = $decoded;
        }

        return is_array($data) ? array_values(array_filter($data, 'is_array')) : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function normalizeAttachments(mixed $value): array
    {
        return self::decodeAttachmentPayload($value);
    }

    /**
     * @param  array<string, mixed>  $attachment
     */
    public static function attachmentFingerprint(array $attachment): string
    {
        $path = ltrim(str_replace('\\', '/', (string) ($attachment['path'] ?? $attachment['file_path'] ?? '')), '/');
        if ($path !== '') {
            return 'path:' . $path;
        }

        $name = (string) ($attachment['original_name'] ?? $attachment['filename'] ?? $attachment['name'] ?? '');
        $type = (string) ($attachment['type'] ?? '');
        $size = (string) ($attachment['size'] ?? '');

        return 'meta:' . $name . '|' . $type . '|' . $size;
    }

    /**
     * @param  array<int, array<string, mixed>>  $left
     * @param  array<int, array<string, mixed>>  $right
     */
    public static function attachmentsAreEquivalent(array $left, array $right): bool
    {
        $fingerprints = static function (array $items): array {
            return collect($items)
                ->map(static fn (array $item) => self::attachmentFingerprint($item))
                ->sort()
                ->values()
                ->all();
        };

        return $fingerprints($left) === $fingerprints($right);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function storedAttachments(): array
    {
        $raw = $this->getRawOriginal('attachment') ?? $this->attributes['attachment'] ?? null;

        return self::normalizeAttachments($raw);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function attachmentsFromMemo(?object $memo): array
    {
        if ($memo === null) {
            return [];
        }

        $fromAccessor = self::decodeAttachmentPayload($memo->attachment ?? null);
        if ($fromAccessor !== []) {
            return $fromAccessor;
        }

        $raw = $memo->getAttributes()['attachment'] ?? null;

        return self::decodeAttachmentPayload($raw);
    }

    public function attachmentsDifferFromParent(?object $parentMemo): bool
    {
        $cr = $this->storedAttachments();
        $parent = self::attachmentsFromMemo($parentMemo);

        if ($cr === [] && $parent === []) {
            return false;
        }

        return ! self::attachmentsAreEquivalent($cr, $parent);
    }

    /**
     * Attachments to embed after the CR PDF when they differ from the parent memo.
     *
     * @return array<int, array<string, mixed>>
     */
    public function attachmentsForPrintAppendix(?object $parentMemo): array
    {
        if (! $this->attachmentsDifferFromParent($parentMemo)) {
            return [];
        }

        return $this->storedAttachments();
    }

    /**
     * Build attachment list from multipart form fields attachments[n][file|type|delete|replace].
     *
     * @param  array<int|string, array<string, mixed>>  $existingAttachments
     * @return array<int, array<string, mixed>>
     */
    public static function collectAttachmentsFromRequest(
        \Illuminate\Http\Request $request,
        array $existingAttachments = [],
        string $uploadDirectory = 'uploads/change-requests'
    ): array {
        $attachmentData = $request->input('attachments', []);
        if (! is_array($attachmentData)) {
            $attachmentData = [];
        }

        if ($attachmentData === [] && ($request->allFiles()['attachments'] ?? []) === []) {
            return array_values($existingAttachments);
        }

        $attachments = [];

        foreach ($attachmentData as $index => $attachmentInfo) {
            if (! is_array($attachmentInfo)) {
                continue;
            }

            $type = $attachmentInfo['type'] ?? 'Document';

            if (isset($attachmentInfo['delete']) && (string) $attachmentInfo['delete'] === '1') {
                continue;
            }

            $file = $request->file('attachments.'.$index.'.file');

            if ($file && $file->isValid()) {
                $extension = strtolower($file->getClientOriginalExtension());
                $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'ppt', 'pptx', 'xls', 'xlsx', 'doc', 'docx'];
                if (! in_array($extension, $allowedExtensions, true)) {
                    continue;
                }

                $filename = time().'_'.uniqid().'.'.$extension;
                $path = $file->storeAs($uploadDirectory, $filename, 'public');

                $attachments[] = [
                    'type' => $type,
                    'filename' => $filename,
                    'original_name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                    'uploaded_at' => now()->toDateTimeString(),
                ];

                continue;
            }

            if (isset($attachmentInfo['replace']) && (string) $attachmentInfo['replace'] === '1') {
                continue;
            }

            if (isset($existingAttachments[$index]) && is_array($existingAttachments[$index])) {
                $attachments[] = $existingAttachments[$index];
            }
        }

        return $attachments;
    }

    public function getAttachmentAttribute($value)
    {
        return self::normalizeAttachments($value);
    }

    public function getInternalParticipantsAttribute($value)
    {
       $data = $this->cleanJson($value);
        
        $result = [];
        foreach ($data as $staffId => $participantDetails) {
            $staff = \App\Models\Staff::find($staffId) ?: \App\Models\Staff::find((int)$staffId);
            if ($staff) {
                $result[] = [
                    'staff' => $staff,
                    'participant_start' => $participantDetails['participant_start'],
                    'participant_end' => $participantDetails['participant_end'],
                    'participant_days' => $participantDetails['participant_days']
                ];
            } else {
                $result[] = [
                    'staff' => null,
                    'participant_start' => $participantDetails['participant_start'],
                    'participant_end' => $participantDetails['participant_end'],
                    'participant_days' => $participantDetails['participant_days']
                ];
            }
        }
        return $result;
    }

    private function cleanJson($value)
    {
         // Remove extra quotes if present
         if (is_string($value) && strlen($value) > 2 && $value[0] === '"' && $value[strlen($value)-1] === '"') {
            $value = substr($value, 1, -1);
        }
        // Unescape slashes
        $value = stripslashes($value);
        // First decode
        $data = json_decode($value, true);
        // If still a string, decode again (double-encoded)
        if (is_string($data)) {
            $data = json_decode($data, true);
        }
        return $data;
    }

    public function getLocationsAttribute()
    {
        $data = $this->cleanJson($this->location_id);
        return Location::whereIn('id', $data)->pluck('name')->implode(', ');
    }

    /**
     * Get the approval trails for this special memo.
     */
    public function approvalTrails()
    {
        return $this->morphMany(ApprovalTrail::class, 'model', 'model_type', 'model_id')->orderBy('created_at', 'desc');
    }
    public function getStatusAttribute(){
        $user = session('user', []);
      

        if(isset($user['staff_id']) && $this->staff_id == $user['staff_id'] ){
         return ($this->forward_workflow_id==null)?'Draft':(($this->overall_status =='approved')?'Approved':'Pending');
        }

        $last_log = ApprovalTrail::where('model_id', $this->id)
            ->where('model_type', 'App\\Models\\ChangeRequest')
            ->where('is_archived', 0)
            ->orderBy('id', 'asc')
            ->first();

        if($last_log)
         return strtoupper($last_log->action);

         if(can_take_action($this))
         return ' Pending';
    }

    /**
     * Get the budget breakdown as an array.
     */
    public function getBudgetBreakdownAttribute($value)
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_string($decoded)) {
                $decoded = json_decode($decoded, true);
            }

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    // JSON-BASED: internal_participants[] mapped to Staff
    public function getInternalParticipantsDetailsAttribute()
    {
        $participantIds = $this->internal_participants ?? [];
        
        // Handle both array and JSON string formats
        if (is_string($participantIds)) {
            $participantIds = json_decode($participantIds, true) ?? [];
        }
        
        // Ensure it's an array and not empty
        if (!is_array($participantIds) || empty($participantIds)) {
            return collect();
        }
        
        // Recursively flatten and extract IDs
        $flatIds = $this->flattenParticipantIds($participantIds);
        
        if (empty($flatIds)) {
            return collect();
        }
        
        return Staff::whereIn('staff_id', $flatIds)->get();
    }
    
    /**
     * Recursively flatten participant IDs from nested arrays
     */
    private function flattenParticipantIds($data)
    {
        $ids = [];
        
        if (is_array($data)) {
            foreach ($data as $key => $item) {
                if (is_array($item)) {
                    // Look for staff_id or id keys within the item
                    if (isset($item['staff_id'])) {
                        $ids[] = $item['staff_id'];
                    } elseif (isset($item['id'])) {
                        $ids[] = $item['id'];
                    } else {
                        // If no staff_id or id found, check if the key itself is a participant ID
                        if (is_numeric($key) || (is_string($key) && is_numeric($key))) {
                            $ids[] = $key;
                        } else {
                            // Recursively process nested arrays
                            $ids = array_merge($ids, $this->flattenParticipantIds($item));
                        }
                    }
                } else {
                    // Direct value - could be the key or the value
                    if (is_numeric($key) || (is_string($key) && is_numeric($key))) {
                        $ids[] = $key;
                    } else {
                        $ids[] = $item;
                    }
                }
            }
        } else {
            // Single value
            $ids[] = $data;
        }
        
        // Clean and validate IDs
        $ids = array_filter($ids, function($id) {
            return !empty($id) && $id !== null && (is_numeric($id) || is_string($id));
        });
        
        return array_values(array_unique($ids));
    }

    public function getResourceUrlAttribute()
    {
        return route('change-requests.show', $this);
    }
     public function getMyLastActionAttribute(){
        $userStaffId = user_session('staff_id');
        if (!$userStaffId) {
            return null;
        }
        
        // Get the current approval level
        $currentApprovalLevel = $this->approval_level;
        if (!$currentApprovalLevel) {
            return null;
        }
        
        // First, check if user has any action at the current approval level
        $currentLevelAction = ApprovalTrail::where('model_id', $this->id)
            ->where('model_type', 'App\\Models\\ChangeRequest')
            ->where('staff_id', $userStaffId)
            ->where('approval_order', $currentApprovalLevel)
            ->where('is_archived', 0)
            ->orderByDesc('id')
            ->first();
        
        if ($currentLevelAction) {
            return $currentLevelAction;
        }
        
        // If no action at current level, check if user has already passed at any previous level
        // This allows previous approvers to see their actions
        $previousPassedAction = ApprovalTrail::where('model_id', $this->id)
            ->where('model_type', 'App\\Models\\ChangeRequest')
            ->where('staff_id', $userStaffId)
            ->where('action', 'approved')
            ->where('approval_order', '<', $currentApprovalLevel)
            ->where('is_archived', 0)
            ->orderByDesc('id')
            ->first();
        
        return $previousPassedAction;
    }
    public function getHasPassedAtCurrentLevelAttribute(){
        $userStaffId = user_session('staff_id');
        if (!$userStaffId || !$this->approval_level) {
            return false;
        }
        
        $currentApprovalLevel = $this->approval_level;
        
        // Check if user has approved at the current approval level
        return ApprovalTrail::where('model_id', $this->id)
            ->where('model_type', 'App\\Models\\ChangeRequest')
            ->where('staff_id', $userStaffId)
            ->where('approval_order', $currentApprovalLevel)
            ->where('action', 'approved')
            ->where('is_archived', 0)
            ->exists();
    }
    public function getMyCurrentLevelActionAttribute(){
        $userStaffId = user_session('staff_id');
        if (!$userStaffId || !$this->approval_level) {
            return null;
        }
        
        $currentApprovalLevel = $this->approval_level;
        
        // Only return actions at the current approval level
        return ApprovalTrail::where('model_id', $this->id)
            ->where('model_type', 'App\\Models\\ChangeRequest')
            ->where('staff_id', $userStaffId)
            ->where('approval_order', $currentApprovalLevel)
            ->where('is_archived', 0)
            ->orderByDesc('id')
            ->first();
    }

    public function getFinalApprovalStatusAttribute(){
        // Get the latest approval trail entry for this change request
        $latestTrail = ApprovalTrail::where('model_id', $this->id)
            ->where('model_type', 'App\\Models\\ChangeRequest')
            ->where('is_archived', 0) // Only consider non-archived trails
            ->orderBy('id', 'desc')
            ->first();
        
        if (!$latestTrail) {
            return 'pending'; // No approval trail yet
        }
        
        // Check if the latest action was 'approved' or 'rejected'
        if (strtolower($latestTrail->action) === 'approved') {
            return 'approved';
        } elseif (strtolower($latestTrail->action) === 'rejected') {
            return 'rejected';
        } else {
            return 'pending'; // Other actions like 'returned', 'submitted', etc.
        }
    }

    /** @return array<int, string> */
    protected function summernoteHtmlFieldsToTrim(): array
    {
        return [
            'supporting_reasons',
            'justification',
            'background',
            'activity_request_remarks',
        ];
    }
}